<?php

namespace App\Application\Admission\Queries;

use App\Application\Contracts\Query;

final readonly class GetApplicationTransfersQuery implements Query
{
    /**
     * @param  list<int>  $allowedSchoolIds  Schools the user is linked to — transfer targets.
     */
    public function __construct(
        public int $schoolId,
        public array $allowedSchoolIds,
        public ?string $search = null,
        public int $page = 1,
        public int $perPage = 20,
    ) {}
}
