<?php

namespace App\Application\Audit\Queries;

final readonly class ListAuditLogsQuery
{
    public function __construct(
        public int $schoolId,
        public ?string $entityType = null,
        public ?int $entityId = null,
        public int $limit = 50,
    ) {}
}
