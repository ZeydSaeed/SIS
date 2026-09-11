<?php

namespace App\Application\Graduation\Queries;

use App\Application\Contracts\Query;

/**
 * UNIT-3C19-02 — read requirement evaluation facts for CompletionOutcome identity.
 */
final readonly class GetRequirementEvaluationsQuery implements Query
{
    public function __construct(
        public int $schoolId,
        public int $enrollmentId,
    ) {}
}
