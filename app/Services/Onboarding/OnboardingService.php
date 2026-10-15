<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Exceptions\HttpException;
use App\Repositories\StokvelRepository;
use App\Services\AuditService;

final class OnboardingService
{
    public const MAX_STEP = 12;

    public function __construct(
        private readonly StokvelRepository $stokvels,
        private readonly AuditService $audit
    ) {
    }

    public function currentStep(int $stokvelId): int
    {
        $row = $this->stokvels->getOnboarding($stokvelId);
        if ($row) {
            return max(1, min(self::MAX_STEP, (int) ($row['current_step'] ?? 1)));
        }
        return 1;
    }

    /** @return array<string, mixed> */
    public function progress(int $stokvelId): array
    {
        $row = $this->stokvels->getOnboarding($stokvelId);
        if (!$row) {
            return ['step' => 1, 'data' => []];
        }

        $data = $row['wizard_data'] ?? '{}';
        if (is_string($data)) {
            $data = json_decode($data, true) ?: [];
        }

        return [
            'step' => $this->currentStep($stokvelId),
            'data' => is_array($data) ? $data : [],
            'completed_at' => $row['completed_at'] ?? null,
        ];
    }

    /** @param array<string, mixed> $stepData */
    public function saveStep(int $stokvelId, int $step, array $stepData, int $userId): array
    {
        if ($step < 1 || $step > self::MAX_STEP) {
            throw new HttpException(422, 'Invalid onboarding step.');
        }

        $state = $this->progress($stokvelId);
        $data = $state['data'];
        $data['step_' . $step] = $stepData;
        $data['last_saved_by'] = $userId;
        $data['last_saved_at'] = now_utc();

        $nextStep = min(self::MAX_STEP, max($step, $this->currentStep($stokvelId)));
        if ($step >= $this->currentStep($stokvelId)) {
            $nextStep = min(self::MAX_STEP, $step + 1);
        }

        $this->stokvels->saveOnboarding($stokvelId, $nextStep, $data);
        $this->audit->log('stokvel.onboarding_saved', 'stokvel', $stokvelId, metadata: [
            'step' => $step,
            'next_step' => $nextStep,
        ]);

        return $this->progress($stokvelId);
    }

    public function activate(int $stokvelId, int $userId): array
    {
        $progress = $this->progress($stokvelId);
        if ($this->currentStep($stokvelId) < self::MAX_STEP) {
            throw new HttpException(422, 'Complete all onboarding steps before activation.');
        }

        $this->stokvels->activate($stokvelId);
        $this->dbMarkCompleted($stokvelId);

        $this->audit->log('stokvel.activated', 'stokvel', $stokvelId, metadata: [
            'user_id' => $userId,
        ]);

        return [
            'stokvel_id' => $stokvelId,
            'progress' => $progress,
        ];
    }

    private function dbMarkCompleted(int $stokvelId): void
    {
        $this->stokvels->getOnboarding($stokvelId);
        try {
            app()->container()->get(\App\Core\Database::class)->exec(
                'UPDATE stokvel_onboarding SET completed_at = :completed_at, current_step = :step, updated_at = :updated_at
                 WHERE stokvel_id = :stokvel_id',
                [
                    'completed_at' => now_utc(),
                    'step' => self::MAX_STEP,
                    'updated_at' => now_utc(),
                    'stokvel_id' => $stokvelId,
                ]
            );
        } catch (\Throwable) {
        }
    }
}
