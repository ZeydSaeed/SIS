<?php

namespace App\Application\Curriculum\Queries;

final readonly class GetPrerequisiteQuery
{
    public function __construct(
        public int $prerequisiteId,
    ) {}
}
