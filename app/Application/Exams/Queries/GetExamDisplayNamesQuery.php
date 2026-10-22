<?php

namespace App\Application\Exams\Queries;

final readonly class GetExamDisplayNamesQuery
{
    /**
     * @param  list<int>  $termIds
     * @param  list<int>  $academicYearIds
     * @param  list<int>  $subjectIds
     */
    public function __construct(
        public array $termIds = [],
        public array $academicYearIds = [],
        public array $subjectIds = [],
    ) {}
}
