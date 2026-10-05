<?php

namespace App\Domain\Admission\Data;

/**
 * Move an admission application to another school / request kind / academic year (period).
 * Unchanged dimensions repeat the "from" value.
 */
final readonly class TransferApplicationData
{
    public function __construct(
        public int $applicationId,
        public int $fromSchoolId,
        public int $toSchoolId,
        public int $fromRequestKind,
        public int $toRequestKind,
        public int $fromPeriodId,
        public int $toPeriodId,
        public int $fromAcademicYearId,
        public int $toAcademicYearId,
        public ?string $reason,
        public ?int $transferredBy,
    ) {}
}
