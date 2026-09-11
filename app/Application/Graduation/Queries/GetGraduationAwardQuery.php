<?php

namespace App\Application\Graduation\Queries;

use App\Application\Contracts\Query;

/**
 * UNIT-3C19-04 — read Graduation Award identity + pointer-only version snapshot.
 */
final readonly class GetGraduationAwardQuery implements Query
{
    public function __construct(
        public int $schoolId,
        public int $enrollmentId,
    ) {}
}
