<?php

namespace App\Application\Attendance\DTOs;

final readonly class AttendanceSessionDTO
{
    /**
     * @param  list<AttendanceRecordDTO>|null  $records
     */
    public function __construct(
        public int $id,
        public int $schoolId,
        public int $sectionId,
        public int $subjectId,
        public int $academicYearId,
        public string $sessionDate,
        public ?int $periodId,
        public int $teacherId,
        public int $status,
        public ?array $records = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'school_id' => $this->schoolId,
            'section_id' => $this->sectionId,
            'subject_id' => $this->subjectId,
            'academic_year_id' => $this->academicYearId,
            'session_date' => $this->sessionDate,
            'period_id' => $this->periodId,
            'teacher_id' => $this->teacherId,
            'status' => $this->status,
        ];

        if ($this->records !== null) {
            $data['records'] = array_map(
                fn (AttendanceRecordDTO $r): array => $r->toArray(),
                $this->records,
            );
        }

        return $data;
    }
}
