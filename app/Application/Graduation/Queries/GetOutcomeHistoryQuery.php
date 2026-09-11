<?php

namespace App\Application\Graduation\Queries;

use App\Application\Contracts\Query;

/**
 * UNIT-3C19-05 — enrollment-scoped historical aggregate (all versions; not preferred/current).
 */
final readonly class GetOutcomeHistoryQuery implements Query
{
    public function __construct(
        public int $schoolId,
        public int $enrollmentId,
    ) {}
}
