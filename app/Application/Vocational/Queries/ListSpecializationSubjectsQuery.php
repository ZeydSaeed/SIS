<?php

namespace App\Application\Vocational\Queries;

final readonly class ListSpecializationSubjectsQuery
{
    public function __construct(
        public int $schoolId,
        public int $specializationId,
        public ?int $status,
    ) {}
}
