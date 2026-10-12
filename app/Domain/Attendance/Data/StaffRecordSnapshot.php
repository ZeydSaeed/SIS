<?php

namespace App\Domain\Attendance\Data;

final readonly class StaffRecordSnapshot
{
    public function __construct(
        public int $id,
        public int $schoolId,
        public int $academicYearId,
        public int $teacherId,
        public string $workDate,
        public int $status,
        public ?string $checkInAt,
        public ?string $checkOutAt,
        public ?int $minutesLate,
        public int $method,
        public ?string $notes,
        public int $version,
    ) {}

    /**
     * @return array<string, int|string|null>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'check_in_at' => $this->checkInAt,
            'check_out_at' => $this->checkOutAt,
            'minutes_late' => $this->minutesLate,
            'method' => $this->method,
            'notes' => $this->notes,
        ];
    }
}
