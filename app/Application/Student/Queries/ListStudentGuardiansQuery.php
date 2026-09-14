<?php

namespace App\Application\Student\Queries;

final readonly class ListStudentGuardiansQuery
{
    public function __construct(
        public int $schoolId,
        public int $studentId,
    ) {}
}
