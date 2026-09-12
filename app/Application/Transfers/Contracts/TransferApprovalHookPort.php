<?php

namespace App\Application\Transfers\Contracts;

interface TransferApprovalHookPort
{
    /**
     * Best-effort: open a Pending approval_request for a new transfer when an active flow exists.
     * Must not throw into the transfer success path for soft-skip cases.
     */
    public function openForNewTransferRequest(
        int $fromSchoolId,
        int $transferRequestId,
        ?int $requestedBy,
    ): void;
}
