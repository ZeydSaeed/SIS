<?php

namespace App\Optimization\SelfHealing;

enum CheckpointStatus: string
{
    case Created = 'CREATED';
    case ExecutionStarted = 'EXECUTION_STARTED';
    case Executed = 'EXECUTED';
    case AfterCaptured = 'AFTER_CAPTURED';
    case GuardEvaluated = 'GUARD_EVALUATED';
    case StabilizationStarted = 'STABILIZATION_STARTED';
    case Accepted = 'ACCEPTED';
    case GuardRejected = 'GUARD_REJECTED';
    case RollbackStarted = 'ROLLBACK_STARTED';
    case RollbackCompleted = 'ROLLBACK_COMPLETED';
    case Rejected = 'REJECTED';
    case Incomplete = 'INCOMPLETE';
    case RecoveryRecorded = 'RECOVERY_RECORDED';

    /** @return list<string> */
    public static function terminal(): array
    {
        return [
            self::Accepted->value,
            self::Rejected->value,
            self::RollbackCompleted->value,
            self::RecoveryRecorded->value,
        ];
    }

    /** @return list<string> */
    public static function incomplete(): array
    {
        return [
            self::ExecutionStarted->value,
            self::Executed->value,
            self::AfterCaptured->value,
            self::GuardEvaluated->value,
            self::Incomplete->value,
        ];
    }
}
