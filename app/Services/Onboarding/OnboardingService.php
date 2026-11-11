<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Core\Database;
use App\Exceptions\HttpException;
use App\Repositories\StokvelRepository;
use App\Services\AuditService;

final class OnboardingService
{
    /** Ordered wizard steps shown to chairpersons for new stokvels. */
    public const STEPS = [
        1 => 'organisation',
        2 => 'type',
        3 => 'contact',
        4 => 'banking',
        5 => 'contributions',
        6 => 'fees',
        7 => 'rules',
        8 => 'invite',
        9 => 'branding',
        10 => 'review',
    ];

    public const MAX_STEP = 10;

    public function __construct(
        private readonly StokvelRepository $stokvels,
        private readonly AuditService $audit,
        private readonly Database $db
    ) {
    }

    /**
     * Legacy stokvels with no onboarding row are treated as already set up.
     * New stokvels get an incomplete row via ensureStarted().
     */
    public function isComplete(int $stokvelId): bool
    {
        $row = $this->stokvels->getOnboarding($stokvelId);
        if ($row === null) {
            return true;
        }

        return !empty($row['completed_at']);
    }

    public function needsSetup(int $stokvelId): bool
    {
        return !$this->isComplete($stokvelId);
    }

    /** Create an incomplete onboarding row for a newly created stokvel. */
    public function ensureStarted(int $stokvelId): void
    {
        if ($this->stokvels->getOnboarding($stokvelId) !== null) {
            return;
        }

        $now = now_utc();
        $this->db->insert(
            'INSERT INTO stokvel_onboarding
                (stokvel_id, current_step, steps_completed, wizard_data, completed_at, created_at, updated_at)
             VALUES
                (:stokvel_id, 1, :steps_completed, :wizard_data, NULL, :created_at, :updated_at)',
            [
                'stokvel_id' => $stokvelId,
                'steps_completed' => json_encode([], JSON_THROW_ON_ERROR),
                'wizard_data' => json_encode([], JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    public function currentStep(int $stokvelId): int
    {
        $row = $this->stokvels->getOnboarding($stokvelId);
        if ($row) {
            return max(1, min(self::MAX_STEP, (int) ($row['current_step'] ?? 1)));
        }

        return 1;
    }

    /** @return array{step:int,data:array<string,mixed>,completed_at:?string,steps_completed:list<int>,percent:int} */
    public function progress(int $stokvelId): array
    {
        $row = $this->stokvels->getOnboarding($stokvelId);
        if (!$row) {
            return [
                'step' => 1,
                'data' => [],
                'completed_at' => null,
                'steps_completed' => [],
                'percent' => 0,
            ];
        }

        $data = $row['wizard_data'] ?? '{}';
        if (is_string($data)) {
            $data = json_decode($data, true) ?: [];
        }
        if (!is_array($data)) {
            $data = [];
        }

        $completed = $row['steps_completed'] ?? '[]';
        if (is_string($completed)) {
            $completed = json_decode($completed, true) ?: [];
        }
        if (!is_array($completed)) {
            $completed = [];
        }
        $completed = array_values(array_unique(array_map('intval', $completed)));

        $step = $this->currentStep($stokvelId);
        $doneCount = !empty($row['completed_at']) ? self::MAX_STEP : count($completed);
        $percent = (int) min(100, (int) round(($doneCount / self::MAX_STEP) * 100));

        return [
            'step' => $step,
            'data' => $data,
            'completed_at' => $row['completed_at'] ?? null,
            'steps_completed' => $completed,
            'percent' => !empty($row['completed_at']) ? 100 : $percent,
        ];
    }

    /** @param array<string, mixed> $stepData */
    public function saveStep(int $stokvelId, int $step, array $stepData, int $userId): array
    {
        if ($step < 1 || $step > self::MAX_STEP) {
            throw new HttpException(422, 'Invalid onboarding step.');
        }

        $this->ensureStarted($stokvelId);
        $state = $this->progress($stokvelId);
        $data = $state['data'];
        $data['step_' . $step] = $stepData;
        $data['last_saved_by'] = $userId;
        $data['last_saved_at'] = now_utc();

        $completed = $state['steps_completed'];
        if (!in_array($step, $completed, true)) {
            $completed[] = $step;
            sort($completed);
        }

        $current = $this->currentStep($stokvelId);
        $nextStep = $step >= $current
            ? min(self::MAX_STEP, $step + 1)
            : $current;

        $this->stokvels->saveOnboarding($stokvelId, $nextStep, $data, $completed);
        $this->applyStepSideEffects($stokvelId, $step, $stepData);

        $this->audit->log('stokvel.onboarding_saved', 'stokvel', $stokvelId, metadata: [
            'step' => $step,
            'next_step' => $nextStep,
            'steps_completed' => $completed,
        ], stokvelId: $stokvelId, userId: $userId);

        return $this->progress($stokvelId);
    }

    public function markComplete(int $stokvelId, int $userId): void
    {
        $this->ensureStarted($stokvelId);
        $now = now_utc();
        $allSteps = array_keys(self::STEPS);
        $this->db->exec(
            'UPDATE stokvel_onboarding
             SET completed_at = COALESCE(completed_at, :completed_at),
                 current_step = :step,
                 steps_completed = :steps_completed,
                 updated_at = :updated_at
             WHERE stokvel_id = :stokvel_id',
            [
                'completed_at' => $now,
                'step' => self::MAX_STEP,
                'steps_completed' => json_encode($allSteps, JSON_THROW_ON_ERROR),
                'updated_at' => $now,
                'stokvel_id' => $stokvelId,
            ]
        );

        $this->audit->log('stokvel.onboarding_completed', 'stokvel', $stokvelId, metadata: [
            'user_id' => $userId,
        ], stokvelId: $stokvelId, userId: $userId);
    }

    public function activate(int $stokvelId, int $userId): array
    {
        // Review is the final step — allow finish once the wizard has reached it.
        if ($this->currentStep($stokvelId) < self::MAX_STEP) {
            throw new HttpException(422, 'Complete the setup steps before finishing the wizard.');
        }

        $this->stokvels->activate($stokvelId);
        $this->markComplete($stokvelId, $userId);

        $this->audit->log('stokvel.activated', 'stokvel', $stokvelId, metadata: [
            'user_id' => $userId,
        ], stokvelId: $stokvelId, userId: $userId);

        return [
            'stokvel_id' => $stokvelId,
            'progress' => $this->progress($stokvelId),
        ];
    }

    /** @param array<string, mixed> $stepData */
    private function applyStepSideEffects(int $stokvelId, int $step, array $stepData): void
    {
        $key = self::STEPS[$step] ?? '';
        $now = now_utc();

        if ($key === 'organisation') {
            $name = trim((string) ($stepData['name'] ?? ''));
            if ($name !== '') {
                $this->db->exec(
                    'UPDATE stokvels SET name = :name, description = :description, registration_number = :reg, updated_at = :ua
                     WHERE id = :id',
                    [
                        'name' => $name,
                        'description' => trim((string) ($stepData['description'] ?? '')) ?: null,
                        'reg' => trim((string) ($stepData['registration_number'] ?? '')) ?: null,
                        'ua' => $now,
                        'id' => $stokvelId,
                    ]
                );
            }
        }

        if ($key === 'type') {
            $type = trim((string) ($stepData['stokvel_type'] ?? ''));
            if ($type !== '') {
                $this->db->exec(
                    'UPDATE stokvels SET stokvel_type = :type, updated_at = :ua WHERE id = :id',
                    ['type' => $type, 'ua' => $now, 'id' => $stokvelId]
                );
            }
        }

        if ($key === 'contributions' || $key === 'branding' || $key === 'fees') {
            $existing = $this->db->fetch(
                'SELECT id FROM stokvel_settings WHERE stokvel_id = :sid LIMIT 1',
                ['sid' => $stokvelId]
            );
            $colour = trim((string) ($stepData['primary_colour'] ?? ''));
            $minContribution = trim((string) ($stepData['contribution_amount'] ?? ''));
            $day = (int) ($stepData['contribution_day'] ?? 0);
            $joining = trim((string) ($stepData['joining_fee'] ?? ''));

            if ($existing) {
                $sets = ['updated_at = :ua'];
                $params = ['ua' => $now, 'sid' => $stokvelId];
                if ($colour !== '' && preg_match('/^#[0-9A-Fa-f]{6}$/', $colour)) {
                    $sets[] = 'branding_primary_colour = :colour';
                    $params['colour'] = $colour;
                }
                if ($minContribution !== '' && is_numeric($minContribution)) {
                    $sets[] = 'minimum_contribution = :minc';
                    $params['minc'] = $minContribution;
                }
                if ($day >= 1 && $day <= 28) {
                    $sets[] = 'payment_due_day = :day';
                    $sets[] = 'contribution_day = :day';
                    $params['day'] = $day;
                }
                if ($joining !== '' && is_numeric($joining)) {
                    $sets[] = 'joining_fee_amount = :jf';
                    $params['jf'] = $joining;
                }
                if (count($sets) > 1) {
                    $this->db->exec(
                        'UPDATE stokvel_settings SET ' . implode(', ', $sets) . ' WHERE stokvel_id = :sid',
                        $params
                    );
                }
            }
        }
    }
}
