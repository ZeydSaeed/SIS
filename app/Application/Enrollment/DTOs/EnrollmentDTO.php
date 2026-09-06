<?php

namespace App\Application\Enrollment\DTOs;

final readonly class EnrollmentDTO
{
    public function __construct(
        public int $id,
        public int $studentId,
        public int $schoolId,
        public int $academicYearId,
        public int $classId,
        public int $sectionId,
        public string $enrollmentNumber,
        public int $status,
        public string $effectiveFrom,
        public ?string $effectiveTo = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->studentId,
            'school_id' => $this->schoolId,
            'academic_year_id' => $this->academicYearId,
            'class_id' => $this->classId,
            'section_id' => $this->sectionId,
            'enrollment_number' => $this->enrollmentNumber,
            'status' => $this->status,
            'effective_from' => $this->effectiveFrom,
            'effective_to' => $this->effectiveTo,
        ];
    }
}
