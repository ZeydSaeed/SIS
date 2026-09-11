<?php

namespace App\Domain\Graduation\Services;

use App\Domain\Graduation\Exceptions\EvaluatorApproverConflictException;

/**
 * Mandatory SoD (Phase 3C.16 human authorization §3.4).
 */
final class EvaluatorApproverSeparation
{
    public static function assertDistinct(?int $evaluatorActorId, int $approverActorId): void
    {
        if ($evaluatorActorId === null) {
            throw EvaluatorApproverConflictException::sameActor();
        }

        if ($evaluatorActorId === $approverActorId) {
            throw EvaluatorApproverConflictException::sameActor();
        }
    }
}
