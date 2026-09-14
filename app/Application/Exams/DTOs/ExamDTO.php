<?php

namespace App\Application\Exams\DTOs;

final readonly class ExamDTO
{
    public function __construct(
        public int $id,
        public int $schoolId,
        public int $academicYearId,
        public int $termId,
        public int $examTypeId,
        public string $name,
        public string $startDate,
        public string $endDate,
        public int $status,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'school_id' => $this->schoolId,
            'academic_year_id' => $this->academicYearId,
            'term_id' => $this->termId,
            'exam_type_id' => $this->examTypeId,
            'name' => $this->name,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'status' => $this->status,
        ];
    }
}
