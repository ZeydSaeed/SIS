<?php

namespace App\Application\Documents\Queries;

final readonly class GetDocumentQuery
{
    public function __construct(
        public int $schoolId,
        public int $documentId,
    ) {}
}
