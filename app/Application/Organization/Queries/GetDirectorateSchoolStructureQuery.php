<?php

namespace App\Application\Organization\Queries;

use App\Application\Contracts\Query;

/**
 * «المديريات والمدارس»: directorates are shared reference data (all listed);
 * schools — and through them branches — are limited to the user's schools.
 */
final readonly class GetDirectorateSchoolStructureQuery implements Query
{
    /**
     * @param  list<int>  $allowedSchoolIds  Schools the user is linked to (tenant scope — resolved server-side).
     */
    public function __construct(
        public array $allowedSchoolIds,
    ) {}
}
