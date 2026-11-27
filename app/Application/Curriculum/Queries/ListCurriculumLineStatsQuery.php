<?php

namespace App\Application\Curriculum\Queries;

/** Per curriculum: subject count, weekly hours, lines with a main teacher → «مكتمل». */
final readonly class ListCurriculumLineStatsQuery
{
    /** @param  list<int>  $curriculumIds */
    public function __construct(
        public int $schoolId,
        public array $curriculumIds,
    ) {}
}
