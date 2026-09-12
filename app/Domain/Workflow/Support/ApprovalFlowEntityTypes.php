<?php

namespace App\Domain\Workflow\Support;

final class ApprovalFlowEntityTypes
{
    public const ALLOWED = [
        'transfer_request',
        'promotion_record',
        'document_file',
        'fee_type',
    ];

    public static function isAllowed(string $entityType): bool
    {
        return in_array($entityType, self::ALLOWED, true);
    }
}
