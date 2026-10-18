<?php

namespace App\Domain\Leave\Data;

use App\Domain\Leave\ValueObjects\LeaveStatus;
use App\Domain\Leave\ValueObjects\LeaveUnit;
use App\Domain\Leave\ValueObjects\RequesterKind;

final readonly class LeaveRequestSnapshot
{
    public function __construct(
        public int $id,
        public int $schoolId,
        public int $academicYearId,
        public int $requestNo,
        public RequesterKind $kind,
        public int $personId,
        public int $leaveTypeId,
        public int $policyId,
        public string $dateFrom,
        public string $dateTo,
        public float $quantity,
        public LeaveUnit $unit,
        public string $reason,
        public LeaveStatus $status,
        public int $approvalLevels,
        public int $approvedLevels,
        public ?int $createdBy,
        public int $version,
    ) {}

    public function teacherId(): ?int
    {
        return $this->kind === RequesterKind::Teacher ? $this->personId : null;
    }

    public function employeeId(): ?int
    {
        return $this->kind === RequesterKind::Employee ? $this->personId : null;
    }
}
