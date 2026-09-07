<?php

namespace App\Optimization\SelfHealing;

use App\Intelligence\Enums\OptimizationOutcome;
use App\Intelligence\Enums\RecommendationStatus;
use App\Intelligence\Models\OptimizationEvent;
use App\Intelligence\Models\Recommendation;
use App\Optimization\Memory\OptimizationHistoryRecorder;
use Illuminate\Support\Facades\Log;

final class RollbackCoordinator
{
    public function __construct(
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

        $recommendationId = $entry['recommendation_id'] ?? null;
        if ($recommendationId !== null) {
            Recommendation::query()
                ->where('id', $recommendationId)
                ->update(['status' => RecommendationStatus::RolledBack->value]);
        }

        $eventCode = $entry['event_code'] ?? null;
        if ($eventCode !== null) {
            OptimizationEvent::query()
                ->where('event_code', $eventCode)
                ->update(['outcome' => OptimizationOutcome::RolledBack->value]);
        }

        $target = $entry['target'] ?? 'unknown';
        $this->cooldown->startCooldown($target);

        Log::warning('Self-healing: rollback during stabilization', [
            'target' => $target,
            'event_code' => $eventCode,
        ]);

        $this->history->record([
            'component' => $target,
            'decision' => 'ROLLBACK',
            'rollback' => true,
            'lessons_learned' => 'Degradation detected during stabilization window',
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
