<?php

namespace App\Application\Graduation\DTOs;

/**
 * Award identity (0..1 per school+enrollment) + optional pointer-resolved version snapshot.
 */
final readonly class GraduationAwardDTO
{
    public function __construct(
        public int $schoolId,
        public int $enrollmentId,
        public int $awardId,
        public int $studentId,
        public int $academicYearId,
        public ?int $specializationId,
        public ?int $currentIssuedVersionId,
        public ?int $createdBy,
        public string $createdAt,
        public ?GraduationAwardVersionDTO $version,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'school_id' => $this->schoolId,
            'enrollment_id' => $this->enrollmentId,
            'award_id' => $this->awardId,
            'student_id' => $this->studentId,
            'academic_year_id' => $this->academicYearId,
            'specialization_id' => $this->specializationId,
            'current_issued_version_id' => $this->currentIssuedVersionId,
            'created_by' => $this->createdBy,
            'created_at' => $this->createdAt,
            'version' => $this->version?->toArray(),
        ];
    }
}
