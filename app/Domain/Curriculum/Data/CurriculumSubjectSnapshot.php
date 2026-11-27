<?php

namespace App\Domain\Curriculum\Data;

final readonly class CurriculumSubjectSnapshot
{
    public function __construct(
        public int $id,
        public int $curriculumId,
        public int $subjectId,
        public ?int $weeklyHours,
        public bool $isRequired,
        public int $subjectOrder,
        public int $status,
        /** «نظري» hours; null when only the total (weeklyHours) is known. */
        public ?int $theoryHours = null,
        /** «تطبيقي / عملي» hours; null when only the total is known. */
        public ?int $practicalHours = null,
    ) {}
}
