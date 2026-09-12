<?php

namespace App\Application\Workflow\Contracts;

interface ApprovalEntityCompletionHookPort
{
    /**
     * Best-effort side effect when an approval request reaches a terminal status.
     */
    public function onFinalDecision(
        int $approvalSchoolId,
        string $entityType,
        int $entityId,
        int $finalStatus,
        ?int $actorUserId,
    ): void;
}
