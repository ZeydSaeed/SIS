<?php

namespace App\Application\Student\Queries;

final readonly class GetStudentDocumentQuery
{
    public function __construct(
        public int $schoolId,
        public int $documentId,
    ) {}
}
