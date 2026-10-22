<?php

namespace App\Application\Exams\Queries;

/** Seating choices (classes / sections of the exam's year) and the active seat count of each session. */
final readonly class GetExamSeatingContextQuery
{
    /**
     * @param  list<int>  $examSessionIds
     */
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public array $examSessionIds,
    ) {}
}
