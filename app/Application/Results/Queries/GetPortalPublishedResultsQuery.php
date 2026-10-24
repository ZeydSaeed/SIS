<?php

namespace App\Application\Results\Queries;

/** Published results of the students a portal user is allowed to see. */
final readonly class GetPortalPublishedResultsQuery
{
    /** @param  list<int>  $studentIds */
    public function __construct(
        public int $schoolId,
        public array $studentIds,
    ) {}
}
