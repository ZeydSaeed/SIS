<?php

namespace App\Application\Exams\Results;

use App\Application\Shared\Results\ApplicationResult;

/**
 * Per-student outcome of seating a class / section: `seated`, `replayed` (same request seen before), `already_seated`,
 * `subject_not_assigned` (the student does not take the session's subject — not seated) or `failed` (the seat rules
 * refused this one student). Students are independent: one refusal never undoes another seat.
 */
final readonly class SeatClassInExamSessionResult extends ApplicationResult
{
    public const OUTCOMES = ['seated', 'replayed', 'already_seated', 'subject_not_assigned', 'failed'];

    /**
     * @param  list<array{enrollment_id: int, status: string, exam_enrollment_id: ?int}>  $outcomes
     * @param  array<string, int>  $counts
     */
    private function __construct(
        bool $success,
        public array $outcomes,
        public array $counts,
    ) {
        parent::__construct($success);
    }

    /**
     * @param  list<array{enrollment_id: int, status: string, exam_enrollment_id: ?int}>  $outcomes
     */
    public static function fromOutcomes(array $outcomes): self
    {
        $counts = array_fill_keys(self::OUTCOMES, 0);
        foreach ($outcomes as $outcome) {
            $counts[$outcome['status']]++;
        }

        return new self($counts['failed'] === 0, $outcomes, $counts);
    }
}
