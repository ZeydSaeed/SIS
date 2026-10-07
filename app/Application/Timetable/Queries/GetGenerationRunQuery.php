<?php

namespace App\Application\Timetable\Queries;

final readonly class GetGenerationRunQuery
{
    public function __construct(
        public int $schoolId,
        public int $runId,
    ) {}
}
