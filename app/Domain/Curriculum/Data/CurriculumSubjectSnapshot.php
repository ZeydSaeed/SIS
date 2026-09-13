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
    ) {}
}
