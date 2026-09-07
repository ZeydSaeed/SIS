<?php

namespace App\Optimization\SelfHealing;

final class CheckpointRecoveryService
{
    /** @var list<string> */
    private const POST_EXECUTION_STATUSES = [
        CheckpointStatus::Executed->value,
        CheckpointStatus::AfterCaptured->value,
        CheckpointStatus::GuardEvaluated->value,
    ];

    public function __construct(
        private readonly CheckpointService $checkpoints,
        private readonly SelfHealingEventLogger $events,
        private readonly SelfHealingStateStore $stateStore,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function recoverIncomplete(): array
    {
        $incomplete = $this->checkpoints->findIncomplete();
        if ($incomplete === []) {
            return [];
        }

        $this->events->emit('RECOVERY_STARTED', ['count' => count($incomplete)]);

        $results = [];
        foreach ($incomplete as $checkpoint) {
            $result = $this->recoverCheckpoint($checkpoint);
            if ($result !== null) {
                $results[] = $result;
            }
        }

        if ($results !== []) {
            $this->stateStore->mutate(function (array $state) use ($results) {
                $state['incomplete_checkpoints_recovered'] = ($state['incomplete_checkpoints_recovered'] ?? 0) + count($results);

                return $state;
            });
        }

        $this->events->emit('RECOVERY_COMPLETED', [
            'recovered' => count($results),
            'auto_retry' => false,
        ]);

        return $results;
    }

    public function blocksAutonomousRetry(string $target): bool
    {
        return $this->checkpoints->findBlockingForTarget($target) !== [];
    }

    /**
     * @param  array<string, mixed>  $checkpoint
     * @return array<string, mixed>|null
     */
    private function recoverCheckpoint(array $checkpoint): ?array
    {
        $id = (string) ($checkpoint['checkpoint_id'] ?? '');
        if ($id === '') {
            return null;
        }

        $previousStatus = (string) ($checkpoint['execution_status'] ?? '');
        $target = (string) ($checkpoint['target'] ?? '');

        if (in_array($previousStatus, self::POST_EXECUTION_STATUSES, true)) {
            $this->checkpoints->transition($id, CheckpointStatus::RecoveryRecorded, [
                'final_outcome' => 'EXECUTED_BUT_NOT_FINALIZED',
                'rollback_status' => 'not_applicable',
                'recovery_reason' => 'Execution may have completed — blind retry blocked',
                'execution_ambiguous' => true,
                'auto_retry' => false,
                'idempotency_class' => 'ambiguous_executed',
            ]);

            $this->events->emit('CHECKPOINT_RECOVERED', [
                'checkpoint_id' => $id,
                'incident_id' => $checkpoint['incident_id'] ?? null,
                'execution_status_was' => $previousStatus,
                'final_outcome' => 'EXECUTED_BUT_NOT_FINALIZED',
                'target' => $target,
            ]);
        } else {
            $this->checkpoints->transition($id, CheckpointStatus::RecoveryRecorded, [
                'final_outcome' => 'INCOMPLETE_NO_AUTO_RETRY',
                'rollback_status' => 'not_applicable',
                'recovery_reason' => 'Worker interrupted — unsafe auto-retry prevented',
                'execution_ambiguous' => false,
                'auto_retry' => false,
                'idempotency_class' => 'unknown_not_executed',
            ]);

            $this->events->emit('CHECKPOINT_RECOVERED', [
                'checkpoint_id' => $id,
                'incident_id' => $checkpoint['incident_id'] ?? null,
                'execution_status_was' => $previousStatus,
                'final_outcome' => 'INCOMPLETE_NO_AUTO_RETRY',
                'target' => $target,
            ]);
        }

        return [
            'checkpoint_id' => $id,
            'outcome' => 'recovery_recorded',
            'auto_retry' => false,
            'final_outcome' => in_array($previousStatus, self::POST_EXECUTION_STATUSES, true)
                ? 'EXECUTED_BUT_NOT_FINALIZED'
                : 'INCOMPLETE_NO_AUTO_RETRY',
        ];
    }
}
