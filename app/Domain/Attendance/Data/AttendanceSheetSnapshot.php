<?php

namespace App\Domain\Attendance\Data;

use App\Domain\Attendance\ValueObjects\SessionStatus;
use App\Domain\Attendance\ValueObjects\SheetStatus;

/** A session read (and row-locked) for a sheet lifecycle step. */
final readonly class AttendanceSheetSnapshot
{
    public function __construct(
        public int $id,
        public int $schoolId,
        public int $academicYearId,
        public int $sectionId,
        public int $subjectId,
        public int $teacherId,
        public ?int $coTeacherId,
        public string $sessionDate,
        public ?int $periodId,
        public int $status,
        public int $sheetStatus,
        public int $version,
        public int $submissionCount,
        public ?int $scheduleId,
        public ?int $groupId,
        public ?int $policyId,
        public ?string $startTime,
        public ?string $endTime,
    ) {}

    public function sheet(): SheetStatus
    {
        return SheetStatus::from($this->sheetStatus);
    }

    public function isCancelled(): bool
    {
        return $this->status === SessionStatus::Cancelled->value;
    }

    public function belongsTo(int $schoolId, int $academicYearId): bool
    {
        return $this->schoolId === $schoolId && $this->academicYearId === $academicYearId;
    }
}
