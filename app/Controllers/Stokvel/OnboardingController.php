<?php

declare(strict_types=1);

namespace App\Controllers\Stokvel;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Exceptions\HttpException;
use App\Repositories\MembershipRepository;
use App\Repositories\StokvelRepository;
use App\Services\Membership\MembershipService;
use App\Services\Onboarding\OnboardingService;
use App\Support\StokvelTypeCatalog;

final class OnboardingController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requireAuth();
        $this->requirePermission('settings.manage');
        $sid = $this->requireStokvel();

        /** @var OnboardingService $onboarding */
        $onboarding = app()->container()->get(OnboardingService::class);

        // Completed setup — hide wizard and send them to the dashboard.
        if ($onboarding->isComplete($sid)) {
            Session::flash('success', 'Setup is already complete. Manage your stokvel from Settings.');
            return $this->redirect('/app');
        }

        $onboarding->ensureStarted($sid);
        $progress = $onboarding->progress($sid);
        $maxReached = max(
            (int) ($progress['step'] ?? 1),
            !empty($progress['steps_completed']) ? (int) max($progress['steps_completed']) : 1
        );
        $step = (int) $request->query('step', (string) ($progress['step'] ?? 1));
        $step = max(1, min(OnboardingService::MAX_STEP, $step));
        // Do not jump ahead of the furthest reached step.
        if ($step > $maxReached) {
            $step = $maxReached;
        }

        $stokvel = $this->db()->fetch('SELECT * FROM stokvels WHERE id = :id LIMIT 1', ['id' => $sid]) ?? [];

        $canInvite = $this->tenant()->can('members.invite');
        $canAssignRoles = $this->tenant()->can('members.manage');
        $pendingInvitations = [];
        if ($canInvite || $this->tenant()->can('members.view')) {
            try {
                /** @var MembershipRepository $memberships */
                $memberships = app()->container()->get(MembershipRepository::class);
                $pendingInvitations = $memberships->listPendingInvitations(100);
            } catch (\Throwable) {
                $pendingInvitations = [];
            }
        }

        return $this->render('stokvel/onboarding/wizard', [
            'title' => 'Stokvel setup',
            'activeNav' => 'onboarding',
            'navContext' => 'app',
            'step' => $step,
            'steps' => OnboardingService::STEPS,
            'progress' => is_array($progress['data'] ?? null) ? $progress['data'] : [],
            'stepsCompleted' => $progress['steps_completed'] ?? [],
            'maxReachedStep' => $maxReached,
            'percent' => (int) ($progress['percent'] ?? 0),
            'stokvel' => $stokvel,
            'stokvelTypeOptions' => StokvelTypeCatalog::activeOptions($this->db()),
            'canInvite' => $canInvite,
            'canAssignRoles' => $canAssignRoles,
            'pendingInvitations' => $pendingInvitations,
        ]);
    }

    public function saveStep(Request $request, string $step): Response
    {
        $this->requireAuth();
        $this->requirePermission('settings.manage');
        $stepNum = (int) $step;
        if (!isset(OnboardingService::STEPS[$stepNum])) {
            return $this->redirect('/app/onboarding');
        }

        $sid = $this->requireStokvel();
        /** @var OnboardingService $onboarding */
        $onboarding = app()->container()->get(OnboardingService::class);
        if ($onboarding->isComplete($sid)) {
            return $this->redirect('/app');
        }

        $payload = $request->all();
        unset($payload['_token'], $payload['_method']);
        $direction = strtolower(trim((string) ($payload['direction'] ?? 'continue')));
        unset($payload['direction']);

        // Brand-new draft path (no tenant yet) — rare; keep for legacy deep-links.
        if ($stepNum === 1 && (int) ($this->stokvelId() ?? 0) === 0 && !empty($payload['name'])) {
            /** @var StokvelRepository $stokvels */
            $stokvels = app()->container()->get(StokvelRepository::class);
            $name = trim((string) $payload['name']);
            $slugBase = preg_replace('/[^a-z0-9]+/', '-', strtolower($name)) ?: 'stokvel';
            $slug = $slugBase . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
            $sid = $stokvels->create([
                'name' => $name,
                'slug' => $slug,
                'status' => 'draft',
                'created_by_user_id' => $this->userId(),
            ]);
            $onboarding->ensureStarted($sid);

            $memberNumber = sprintf('M-%05d', 1);
            $membershipId = (int) $this->db()->insert(
                'INSERT INTO memberships (stokvel_id, user_id, member_number, status, joined_at, approved_at, created_at, updated_at)
                 VALUES (:sid, :uid, :mn, :st, :ja, :aa, :ca, :ua)',
                [
                    'sid' => $sid,
                    'uid' => $this->userId(),
                    'mn' => $memberNumber,
                    'st' => 'active',
                    'ja' => now_utc(),
                    'aa' => now_utc(),
                    'ca' => now_utc(),
                    'ua' => now_utc(),
                ]
            );
            $role = $this->db()->fetch(
                "SELECT id FROM roles WHERE code = 'chairperson' AND stokvel_id IS NULL LIMIT 1"
            );
            if ($role) {
                $this->db()->insert(
                    'INSERT INTO membership_roles (stokvel_id, membership_id, role_id, assigned_by_user_id, assigned_at, created_at)
                     VALUES (:sid, :mid, :rid, :by, :aa, :ca)',
                    [
                        'sid' => $sid,
                        'mid' => $membershipId,
                        'rid' => (int) $role['id'],
                        'by' => $this->userId(),
                        'aa' => now_utc(),
                        'ca' => now_utc(),
                    ]
                );
            }

            Session::set('active_stokvel_id', $sid);
            Session::set('stokvel_id', $sid);
        }

        try {
            $onboarding->saveStep($sid, $stepNum, $payload, (int) $this->userId());
        } catch (HttpException $e) {
            Session::flash('error', $e->getMessage());
            return $this->redirect('/app/onboarding?step=' . $stepNum);
        }

        $successMessage = 'Step saved.';
        if ((OnboardingService::STEPS[$stepNum] ?? '') === 'invite') {
            $inviteMessage = $this->sendWizardInvites($payload);
            if ($inviteMessage !== null && $inviteMessage !== '') {
                $successMessage = $inviteMessage;
            }
        }

        Session::flash('success', $successMessage);
        if ($direction === 'back' && $stepNum > 1) {
            return $this->redirect('/app/onboarding?step=' . ($stepNum - 1));
        }

        $next = min(OnboardingService::MAX_STEP, $stepNum + 1);
        return $this->redirect('/app/onboarding?step=' . $next);
    }

    public function complete(Request $request): Response
    {
        $this->requireAuth();
        $this->requirePermission('settings.manage');
        $sid = $this->requireStokvel();

        /** @var OnboardingService $onboarding */
        $onboarding = app()->container()->get(OnboardingService::class);
        if ($onboarding->isComplete($sid)) {
            return $this->redirect('/app');
        }

        try {
            $onboarding->activate($sid, (int) $this->userId());
        } catch (HttpException $e) {
            Session::flash('error', $e->getMessage());
            return $this->redirect('/app/onboarding');
        }

        Session::forget('onboarding_step');
        Session::flash('success', 'Setup complete. Your stokvel is ready.');
        return $this->redirect('/app');
    }

    /** @param array<string, mixed> $payload */
    private function sendWizardInvites(array $payload): ?string
    {
        if (!$this->tenant()->can('members.invite')) {
            Session::flash('warning', 'Invites were not sent — you need the members.invite permission.');
            return null;
        }

        $emails = $this->parseInviteEmails($payload);
        if ($emails === []) {
            return null;
        }

        $canAssignRoles = $this->tenant()->can('members.manage');
        $roleCode = trim((string) ($payload['invite_role'] ?? 'ordinary_member'));
        if ($roleCode === '') {
            $roleCode = 'ordinary_member';
        }
        if (!$canAssignRoles) {
            $roleCode = 'ordinary_member';
        }

        $allowed = $canAssignRoles
            ? ['ordinary_member', 'committee_member', 'secretary', 'treasurer']
            : ['ordinary_member'];
        if (!in_array($roleCode, $allowed, true)) {
            $roleCode = 'ordinary_member';
        }

        $role = $this->db()->fetch(
            'SELECT id FROM roles WHERE code = :code AND stokvel_id IS NULL LIMIT 1',
            ['code' => $roleCode]
        );
        $roleId = $role ? (int) $role['id'] : null;

        /** @var MembershipService $memberships */
        $memberships = app()->container()->get(MembershipService::class);
        $sent = [];
        $failed = [];

        foreach ($emails as $email) {
            try {
                $memberships->invite($email, null, $roleId, (int) $this->userId());
                $sent[] = $email;
            } catch (\Throwable $e) {
                $failed[] = $email . ($e->getMessage() !== '' ? ' (' . $e->getMessage() . ')' : '');
            }
        }

        if ($failed !== []) {
            Session::flash('error', 'Could not invite: ' . implode('; ', $failed));
        }

        if ($sent === []) {
            return null;
        }

        return count($sent) === 1
            ? 'Invitation sent to ' . $sent[0] . '.'
            : 'Invitations sent to ' . count($sent) . ' members.';
    }

    /**
     * Accept textarea (one per line / comma-separated), invite_emails[], or single invite_email.
     *
     * @param array<string, mixed> $payload
     * @return list<string>
     */
    private function parseInviteEmails(array $payload): array
    {
        $raw = [];
        if (isset($payload['invite_emails']) && is_array($payload['invite_emails'])) {
            foreach ($payload['invite_emails'] as $item) {
                $raw[] = (string) $item;
            }
        } elseif (isset($payload['invite_emails'])) {
            $raw[] = (string) $payload['invite_emails'];
        }
        if (!empty($payload['invite_email'])) {
            $raw[] = (string) $payload['invite_email'];
        }

        $emails = [];
        foreach ($raw as $chunk) {
            foreach (preg_split('/[\s,;]+/', $chunk) ?: [] as $piece) {
                $email = strtolower(trim($piece));
                if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $emails[$email] = $email;
                }
            }
        }

        return array_values($emails);
    }
}
