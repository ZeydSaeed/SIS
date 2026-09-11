<?php

namespace App\Application\Graduation\Queries;

use App\Application\Contracts\Query;

/**
 * UNIT-3C19-03 — read Graduation Approval attempt facts for preferred completion version.
 */
final readonly class GetGraduationApprovalQuery implements Query
{
    public function __construct(
        public int $schoolId,
        public int $enrollmentId,
    ) {}
}
