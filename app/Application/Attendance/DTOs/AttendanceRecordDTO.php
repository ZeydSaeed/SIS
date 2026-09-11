<?php

namespace App\Application\Attendance\DTOs;

final readonly class AttendanceRecordDTO
{
    public function __construct(
        public int $id,
        public int $sessionId,
        public int $studentId,
        public int $enrollmentId,
        public int $academicYearId,
        public int $schoolId,
        public string $attendanceDate,
        public int $status,
        public ?string $notes,
        public ?int $recordedBy,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'session_id' => $this->sessionId,
            'student_id' => $this->studentId,
            'enrollment_id' => $this->enrollmentId,
            'academic_year_id' => $this->academicYearId,
            'school_id' => $this->schoolId,
            'attendance_date' => $this->attendanceDate,
            'status' => $this->status,
            'notes' => $this->notes,
            'recorded_by' => $this->recordedBy,
        ];
    }
}
