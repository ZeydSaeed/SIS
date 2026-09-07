<?php

namespace App\Security\Audit\Contracts;

use App\Models\User;
use App\Security\Audit\SecurityEventType;

interface SecurityAuditLoggerInterface
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function record(
        SecurityEventType $event,
        string $action,
        string $result,
        ?User $actor = null,
        ?string $target = null,
        array $context = [],
    ): void;
}
