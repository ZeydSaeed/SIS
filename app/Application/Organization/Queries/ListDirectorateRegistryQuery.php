<?php

namespace App\Application\Organization\Queries;

use App\Application\Contracts\Query;

/**
 * Directorates are shared reference data (not tenant rows); school counts are
 * limited to the schools the user is linked to.
 */
final readonly class ListDirectorateRegistryQuery implements Query
{
    /**
     * @param  list<int>  $allowedSchoolIds
     */
    public function __construct(
        public array $allowedSchoolIds,
    ) {}
}
