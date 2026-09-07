<?php

namespace App\Optimization\SelfHealing;

use App\Optimization\Memory\OptimizationHistoryRecorder;
use App\Optimization\Rollback\RollbackManager;

final class RollbackCoordinator
{
    public function __construct(
        private readonly RollbackManager $rollbackManager,
        private readonly OptimizationHistoryRecorder $history,
        private readonly CircuitBreaker $circuitBreaker,
        private readonly OptimizationCooldownManager $cooldown,
    ) {}

    /**
     * @param  array<string, mixed>  $runResult
     */
    public function handleFailedOptimization(array $runResult): void
    {
        $reason = $runResult['reason'] ?? 'Optimization failed';
        $this->circuitBreaker->recordFailure($reason);

        $this->history->record([
            'decision' => 'ROLLBACK',
            'rollback' => true,
            'actual_result' => $runResult,
            'lessons_learned' => $reason,
        ]);
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    public function rollbackFromStabilization(array $entry): void
    {
        if (! config('optimization.gates.rollback_enabled', true)) {
            return;
        }

        $target = $entry['target'] ?? 'unknown';
        $this->cooldown->startCooldown($target);

        $this->history->record([
            'component' => $target,
            'decision' => 'ROLLBACK',
            'rollback' => true,
            'rollback_supported' => false,
            'lessons_learned' => 'Degradation detected during stabilization window — status marked only',
        ]);
    }

    public function rollbackByHistoryId(string $historyId): bool
    {
        $path = config('optimization.history_path')."/{$historyId}.json";
        if (! file_exists($path)) {
            return false;
        }

        $record = json_decode(file_get_contents($path), true);
        if (! is_array($record)) {
            return false;
        }

        $this->rollbackFromStabilization([
            'recommendation_id' => $record['recommendation_id'] ?? null,
            'event_code' => $record['actual_result']['event_code'] ?? null,
            'target' => $record['component'] ?? 'unknown',
        ]);

        return true;
    }
}
