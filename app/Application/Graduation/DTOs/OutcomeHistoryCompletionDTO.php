<?php

namespace App\Application\Graduation\DTOs;

/**
 * CompletionOutcome head + all persisted versions (historical; not preferred selection).
 *
 * @phpstan-type VersionList list<OutcomeHistoryCompletionVersionDTO>
 */
final readonly class OutcomeHistoryCompletionDTO
{
    /**
     * @param list<OutcomeHistoryCompletionVersionDTO> $versions
     */
    public function __construct(
        public int $completionOutcomeId,
        public int $studentId,
        public int $academicYearId,
        public ?int $specializationId,
        public ?int $currentOfficialVersionId,
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
            'completion_outcome_id' => $this->completionOutcomeId,
            'student_id' => $this->studentId,
            'academic_year_id' => $this->academicYearId,
            'specialization_id' => $this->specializationId,
            'current_official_version_id' => $this->currentOfficialVersionId,
            'created_by' => $this->createdBy,
            'created_at' => $this->createdAt,
            'versions' => array_map(
                static fn (OutcomeHistoryCompletionVersionDTO $v) => $v->toArray(),
                $this->versions,
            ),
        ];
    }
}
