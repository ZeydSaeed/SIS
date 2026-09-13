<?php

namespace App\Application\Audit\Queries;

final readonly class GetAuditLogQuery
{
    public function __construct(
        public int $schoolId,
        public int $auditLogId,
    ) {}
}
