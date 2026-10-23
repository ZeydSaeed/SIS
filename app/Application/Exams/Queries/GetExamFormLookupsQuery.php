<?php

namespace App\Application\Exams\Queries;

/** Choices of the create-exam form (terms, exam types) or of the create-session form (subjects). */
final readonly class GetExamFormLookupsQuery
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public bool $forExam,
        public bool $forSession,
    ) {}
}
