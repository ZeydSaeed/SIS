<?php

namespace App\Application\Hr\Queries;

final readonly class GetJobPositionQuery
{
    public function __construct(
        public int $schoolId,
        public int $jobPositionId,
    ) {}
}
