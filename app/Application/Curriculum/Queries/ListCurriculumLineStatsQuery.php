<?php

namespace App\Application\Curriculum\Queries;

/** Per curriculum: subject count, weekly hours, lines with a main teacher → «مكتمل». */
final readonly class ListCurriculumLineStatsQuery
{
    /**
     * @param  list<int>  $curriculumIds
     * @param  array<int, ?int>  $plannedCounts  curriculum id → «عدد المواد» it should have (null = not stated)
     */
    public function __construct(
        public int $schoolId,
        public array $curriculumIds,
        public array $plannedCounts = [],
    ) {}
}
