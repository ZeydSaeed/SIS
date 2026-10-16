<?php

namespace App\Application\Admission\Queries;

use App\Application\Contracts\Query;

/**
 * «متابعة طلبات التقديم» › applications of one school: their data, documents and status history.
 * The school is one of the user's schools (checked by the caller) — the roster spans them all.
 */
final readonly class GetFollowUpApplicationsQuery implements Query
{
    /**
     * @param  list<int>  $applicationIds
     */
    public function __construct(
        public int $schoolId,
        public array $applicationIds,
    ) {}
}
