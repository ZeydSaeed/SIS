<?php

namespace App\Optimization\Rollback;

use App\Intelligence\Enums\OptimizationOutcome;
use App\Intelligence\Enums\RecommendationStatus;
use App\Intelligence\Models\OptimizationEvent;
use App\Intelligence\Models\Recommendation;
use App\Optimization\SelfHealing\SelfHealingEventLogger;

final class RollbackManager
{
    /** @var list<RollbackStrategy> */
    private array $strategies;

    public function __construct(
        NonReversibleRollbackStrategy $analyzeStrategy,
        private readonly SelfHealingEventLogger $events,
    ) {
        $this->strategies = [$analyzeStrategy];
    }

    public function supports(string $actionType): bool
    {
        return $this->strategyFor($actionType) !== null;
    }

    public function isRollbackSupported(string $actionType): bool
    {
        return $this->strategyFor($actionType)?->isRollbackSupported() ?? false;
    }

    public function restoreStrategy(string $actionType): string
    {
        return $this->strategyFor($actionType)?->restoreStrategy() ?? 'unknown';
    }

    /**
     * @param  array<string, mixed>  $checkpoint
     * @return array{rolled_back: bool, reason: ?string, rollback_supported: bool}
     */
    public function rollback(string $actionType, Recommendation $recommendation, ?OptimizationEvent $event, array $checkpoint): array
    {
        $strategy = $this->strategyFor($actionType);
        if ($strategy === null) {
            return ['rolled_back' => false, 'reason' => 'No rollback strategy', 'rollback_supported' => false];
        }

        $this->events->emit('ROLLBACK_STARTED', [
            'checkpoint_id' => $checkpoint['checkpoint_id'] ?? null,
            'recommendation_id' => $recommendation->id,
            'action' => $actionType,
            'rollback_supported' => $strategy->isRollbackSupported(),
        ]);

        $result = $strategy->rollback($recommendation, $event, $checkpoint);

        if ($strategy->isRollbackSupported() && ($result['rolled_back'] ?? false)) {
            $this->markStatusRollback($recommendation, $event);
        } elseif (! $strategy->isRollbackSupported()) {
            $this->markStatusRejected($recommendation, $event);
        }

        $this->events->emit('ROLLBACK_COMPLETED', [
            'checkpoint_id' => $checkpoint['checkpoint_id'] ?? null,
            'recommendation_id' => $recommendation->id,
            'rolled_back' => $result['rolled_back'] ?? false,
            'rollback_supported' => $strategy->isRollbackSupported(),
        ]);

        return array_merge($result, ['rollback_supported' => $strategy->isRollbackSupported()]);
    }

    private function strategyFor(string $actionType): ?RollbackStrategy
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($actionType)) {
                return $strategy;
            }
        }

        return null;
    }

    private function markStatusRollback(Recommendation $recommendation, ?OptimizationEvent $event): void
    {
        $recommendation->update(['status' => RecommendationStatus::RolledBack->value]);
        if ($event !== null) {
            $event->update(['outcome' => OptimizationOutcome::RolledBack->value]);
        }
    }

    private function markStatusRejected(Recommendation $recommendation, ?OptimizationEvent $event): void
    {
        $recommendation->update(['status' => RecommendationStatus::RolledBack->value]);
        if ($event !== null) {
            $event->update(['outcome' => OptimizationOutcome::RolledBack->value]);
        }
    }
}
