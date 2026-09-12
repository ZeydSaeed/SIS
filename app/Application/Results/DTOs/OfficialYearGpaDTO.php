<?php

namespace App\Application\Results\DTOs;

final readonly class OfficialYearGpaDTO
{
    public function __construct(
        public int $schoolId,
        public int $enrollmentId,
        public int $academicYearId,
        public int $gpaResultId,
        public int $resultVersion,
        public ?string $gpaValue,
        public string $scaleCode,
        public bool $incomplete,
        public string $sourceFingerprint,
    ) {}
}
