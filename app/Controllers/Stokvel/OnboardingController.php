<?php

declare(strict_types=1);

namespace App\Controllers\Stokvel;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Exceptions\HttpException;
use App\Repositories\StokvelRepository;
use App\Services\Onboarding\OnboardingService;

final class OnboardingController extends Controller
{
    private const STEPS = [
        1 => 'organisation',
        2 => 'contact',
        3 => 'banking',
        4 => 'contributions',
        5 => 'fees',
        6 => 'loans',
        7 => 'payouts',
        8 => 'rules',
        9 => 'invite',
        10 => 'review',
        11 => 'branding',
        12 => 'activate',
    ];

    public function index(Request $request): Response
    {
        $this->requireAuth();
        $sid = $this->stokvelId();
        $step = 1;
        $progressData = [];

        if ($sid) {
            /** @var OnboardingService $onboarding */
            $onboarding = app()->container()->get(OnboardingService::class);
            $progress = $onboarding->progress($sid);
            $step = (int) ($progress['step'] ?? 1);
            $progressData = is_array($progress['data'] ?? null) ? $progress['data'] : [];
        } else {
            $step = (int) (Session::get('onboarding_step') ?? 1);
        }

        $step = max(1, min(count(self::STEPS), $step));

        return $this->render('stokvel/onboarding/wizard', [
            'title' => 'Stokvel setup',
            'activeNav' => 'onboarding',
            'navContext' => 'app',
            'step' => $step,
            'steps' => self::STEPS,
            'progress' => $progressData,
        ]);
    }

    public function saveStep(Request $request, string $step): Response
    {
        $this->requireAuth();
        $stepNum = (int) $step;
        if (!isset(self::STEPS[$stepNum])) {
            return $this->redirect('/app/onboarding');
        }

        $payload = $request->all();
        unset($payload['_token'], $payload['_method']);

        $sid = (int) ($this->stokvelId() ?? 0);

        if ($stepNum === 1 && $sid === 0 && !empty($payload['name'])) {
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

        if ($sid > 0) {
            /** @var OnboardingService $onboarding */
            $onboarding = app()->container()->get(OnboardingService::class);
            $onboarding->saveStep($sid, $stepNum, $payload, (int) $this->userId());
        } else {
            Session::set('onboarding_step', min($stepNum + 1, count(self::STEPS)));
        }

        Session::flash('success', 'Step saved.');
        return $this->redirect('/app/onboarding');
    }

    public function complete(Request $request): Response
    {
        $this->requireAuth();
        $sid = $this->stokvelId();
        if (!$sid) {
            Session::flash('error', 'Create a stokvel before activating.');
            return $this->redirect('/app/onboarding');
        }

        try {
            /** @var OnboardingService $onboarding */
            $onboarding = app()->container()->get(OnboardingService::class);
            // Allow activation from review step for the 10-step UI while service tracks up to 12.
            $progress = $onboarding->progress($sid);
            $current = (int) ($progress['step'] ?? 1);
            if ($current < 10) {
                throw new HttpException(422, 'Complete all onboarding steps before activation.');
            }
            app()->container()->get(StokvelRepository::class)->activate($sid);
            $this->db()->exec(
                'UPDATE stokvel_onboarding SET completed_at = :completed_at, current_step = :step, updated_at = :updated_at
                 WHERE stokvel_id = :stokvel_id',
                [
                    'completed_at' => now_utc(),
                    'step' => OnboardingService::MAX_STEP,
                    'updated_at' => now_utc(),
                    'stokvel_id' => $sid,
                ]
            );
        } catch (HttpException $e) {
            Session::flash('error', $e->getMessage());
            return $this->redirect('/app/onboarding');
        }

        Session::forget('onboarding_step');
        Session::flash('success', 'Your stokvel is ready.');
        return $this->redirect('/app');
    }
}
