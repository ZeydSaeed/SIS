<?php

namespace App\Application\Attendance\DTOs;

final readonly class DailySectionSummaryDTO
{
    public function __construct(
        public int $sectionId,
        public int $schoolId,
        public int $academicYearId,
        public string $attendanceDate,
        public int $totalStudents,
        public int $presentCount,
        public int $absentCount,
        public int $lateCount,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'section_id' => $this->sectionId,
            'school_id' => $this->schoolId,
            'academic_year_id' => $this->academicYearId,
            'attendance_date' => $this->attendanceDate,
            'total_students' => $this->totalStudents,
            'present_count' => $this->presentCount,
            'absent_count' => $this->absentCount,
            'late_count' => $this->lateCount,
        ];
    }
}
