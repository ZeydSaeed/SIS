<?php

namespace App\Domain\Audit\Services;

final class RegisterAuditLogGuard
{
    public function rejectionCode(string $action, string $entityType): ?string
    {
        if (trim($action) === '') {
            return 'audit.action_invalid';
        }
        if (strlen(trim($action)) > 50) {
            return 'audit.action_too_long';
        }
        if (trim($entityType) === '') {
            return 'audit.entity_type_invalid';
        }
        if (strlen(trim($entityType)) > 50) {
            return 'audit.entity_type_too_long';
        }

        return null;
    }
}
