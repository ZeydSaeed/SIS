<?php

namespace App\Application\Results\DTOs;

final readonly class OfficialTermResultListItemDTO
{
    public function __construct(
        public int $schoolId,
        public int $enrollmentId,
        public int $academicYearId,
        public int $termResultId,
        public int $termId,
        public int $subjectId,
        public ?string $weightedTotal,
        public ?int $passFail,
        public bool $incomplete,
    ) {}
}
