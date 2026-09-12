<?php

namespace App\Application\Vocational\Queries;

use App\Application\Contracts\Query;

final readonly class GetSpecializationQuery implements Query
{
    public function __construct(
        public int $schoolId,
        public int $specializationId,
    ) {}
}
