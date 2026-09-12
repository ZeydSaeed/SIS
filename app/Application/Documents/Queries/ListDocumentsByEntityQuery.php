<?php

namespace App\Application\Documents\Queries;

final readonly class ListDocumentsByEntityQuery
{
    public function __construct(
        public int $schoolId,
        public string $entityType,
        public int $entityId,
    ) {}
}
