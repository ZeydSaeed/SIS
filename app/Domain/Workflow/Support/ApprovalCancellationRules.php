<?php

namespace App\Domain\Workflow\Support;

use App\Domain\Workflow\ValueObjects\ApprovalRequestStatus;

final class ApprovalCancellationRules
{
    /**
     * @return list<string>
     */
    public static function validateCancellable(int $status): array
    {
        if ($status !== ApprovalRequestStatus::Pending) {
            return ['workflow.approval_request_not_cancellable'];
        }

        return [];
    }

    /**
     * @return array{status:int, completed:bool}
     */
    public static function applyCancel(): array
    {
        return [
            'status' => ApprovalRequestStatus::Cancelled,
            'completed' => true,
        ];
    }
}
