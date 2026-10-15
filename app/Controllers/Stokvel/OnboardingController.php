<?php

declare(strict_types=1);

namespace App\Controllers\Stokvel;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;

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
    ];

    public function index(Request $request): Response
    {
        $this->requireAuth();
        $step = (int) (Session::get('onboarding_step') ?? 1);
        $step = max(1, min(count(self::STEPS), $step));
        return $this->render('stokvel/onboarding/wizard', [
            'title' => 'Stokvel setup',
            'activeNav' => 'onboarding',
            'navContext' => 'app',
            'step' => $step,
            'steps' => self::STEPS,
            'progress' => $this->loadProgress(),
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

        $this->service(\App\Services\Stokvel\OnboardingService::class, fn ($db) => new class($db) {
            public function __construct(private $db) {}
            public function save(int $userId, int $step, array $data): void
            {
                $this->db->exec(
                    'INSERT INTO onboarding_progress (user_id, step, payload_json, updated_at) VALUES (:uid, :step, :pj, :ua)
                     ON DUPLICATE KEY UPDATE payload_json = VALUES(payload_json), updated_at = VALUES(updated_at)',
                    ['uid' => $userId, 'step' => $step, 'pj' => json_encode($data), 'ua' => now_utc()]
                );
            }
        })->save((int) $this->userId(), $stepNum, $payload);

        if ($stepNum === 1 && !empty($payload['name'])) {
            $stokvelId = (int) Session::get('active_stokvel_id', 0);
            if ($stokvelId === 0) {
                $stokvelId = (int) $this->db()->insert(
                    'INSERT INTO stokvels (name, status, created_at, updated_at) VALUES (:name, :st, :ca, :ua)',
                    ['name' => trim((string) $payload['name']), 'st' => 'draft', 'ca' => now_utc(), 'ua' => now_utc()]
                );
                $this->db()->insert(
                    'INSERT INTO memberships (stokvel_id, user_id, role, status, joined_at, created_at, updated_at) VALUES (:sid, :uid, :role, :st, :ja, :ca, :ua)',
                    ['sid' => $stokvelId, 'uid' => $this->userId(), 'role' => 'chairperson', 'st' => 'active', 'ja' => now_utc(), 'ca' => now_utc(), 'ua' => now_utc()]
                );
                Session::set('active_stokvel_id', $stokvelId);
            }
        }

        Session::set('onboarding_step', min($stepNum + 1, count(self::STEPS)));
        Session::flash('success', 'Step saved.');
        return $this->redirect('/app/onboarding');
    }

    public function complete(Request $request): Response
    {
        $this->requireAuth();
        $sid = $this->stokvelId();
        if ($sid) {
            $this->db()->exec('UPDATE stokvels SET status = :st, activated_at = :aa, updated_at = :ua WHERE id = :id', [
                'st' => 'active',
                'aa' => now_utc(),
                'ua' => now_utc(),
                'id' => $sid,
            ]);
        }
        Session::forget('onboarding_step');
        Session::flash('success', 'Your stokvel is ready.');
        return $this->redirect('/app');
    }

    private function loadProgress(): array
    {
        $rows = $this->db()->fetchAll(
            'SELECT step, payload_json FROM onboarding_progress WHERE user_id = :uid',
            ['uid' => $this->userId()]
        );
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['step']] = json_decode((string) $row['payload_json'], true) ?: [];
        }
        return $out;
    }
}
