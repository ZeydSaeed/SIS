<?php

namespace App\Application\Organization\Queries;

use App\Application\Contracts\Query;

final readonly class GetBranchStructureQuery implements Query
{
    public function __construct(
        public int $schoolId,
        /** false on the «الفروع والاختصاصات» page (every status); true for forms / lists. */
        public bool $activeOnly = true,
    ) {}
}
