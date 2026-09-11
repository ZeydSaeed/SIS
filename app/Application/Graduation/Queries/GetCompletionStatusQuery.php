<?php

namespace App\Application\Graduation\Queries;

use App\Application\Contracts\Query;

/**
 * UNIT-3C19-01 — read CompletionOutcome SSOT by school + enrollment identity.
 */
final readonly class GetCompletionStatusQuery implements Query
{
    public function __construct(
        public int $schoolId,
        public int $enrollmentId,
    ) {}
}
