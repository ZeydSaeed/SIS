<?php

namespace App\Application\Graduation\DTOs;

/**
 * GraduationAward head + all persisted versions (enrollment peer; not outcome child).
 *
 * @phpstan-type VersionList list<OutcomeHistoryAwardVersionDTO>
 */
final readonly class OutcomeHistoryAwardDTO
{
    /**
     * @param list<OutcomeHistoryAwardVersionDTO> $versions
     */
    public function __construct(
        public int $awardId,
        public int $studentId,
        public int $academicYearId,
        public ?int $specializationId,
        public ?int $currentIssuedVersionId,
        public ?int $createdBy,
        public string $createdAt,
        public array $versions,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'award_id' => $this->awardId,
            'student_id' => $this->studentId,
            'academic_year_id' => $this->academicYearId,
            'specialization_id' => $this->specializationId,
            'current_issued_version_id' => $this->currentIssuedVersionId,
            'created_by' => $this->createdBy,
            'created_at' => $this->createdAt,
            'versions' => array_map(
                static fn (OutcomeHistoryAwardVersionDTO $v) => $v->toArray(),
                $this->versions,
            ),
        ];
    }
}
