<?php

namespace App\Application\Graduation\DTOs;

/**
 * Enrollment-scoped historical aggregate (3C.19.5 Design Lock).
 *
 * Always returned for a school+enrollment lookup — missing completion and/or award
 * yield null sections; never throws CompletionOutcomeNotFoundException.
 */
final readonly class OutcomeHistoryDTO
{
    public function __construct(
        public int $schoolId,
        public int $enrollmentId,
        public ?OutcomeHistoryCompletionDTO $completionOutcome,
        public ?OutcomeHistoryAwardDTO $award,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'school_id' => $this->schoolId,
            'enrollment_id' => $this->enrollmentId,
            'completion_outcome' => $this->completionOutcome?->toArray(),
            'award' => $this->award?->toArray(),
        ];
    }
}
