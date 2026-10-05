<?php

namespace App\Application\Organization\Queries;

use App\Application\Contracts\Query;

final readonly class ListSchoolRegistryQuery implements Query
{
    /**
     * @param  list<int>  $allowedSchoolIds  Schools the user is linked to (tenant scope — resolved server-side).
     */
    public function __construct(
        public array $allowedSchoolIds,
    ) {}
}
