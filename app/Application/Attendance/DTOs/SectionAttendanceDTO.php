<?php

namespace App\Application\Attendance\DTOs;

final readonly class SectionAttendanceDTO
{
    /**
     * @param  list<AttendanceSessionDTO>  $sessions
     * @param  list<AttendanceRecordDTO>  $records
     */
    public function __construct(
        public int $schoolId,
        public int $sectionId,
        public string $date,
        public int $academicYearId,
        public array $sessions,
        public array $records,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'school_id' => $this->schoolId,
            'section_id' => $this->sectionId,
            'date' => $this->date,
            'academic_year_id' => $this->academicYearId,
            'sessions' => array_map(fn (AttendanceSessionDTO $s): array => $s->toArray(), $this->sessions),
            'records' => array_map(fn (AttendanceRecordDTO $r): array => $r->toArray(), $this->records),
        ];
    }
}
