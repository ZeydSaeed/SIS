<?php

namespace App\Application\Exams\Results;

use App\Application\Shared\Results\ApplicationResult;

/**
 * Per-row outcome of a grade-entry sheet: `saved` (new grade), `replayed` (same submission seen before)
 * or an error code. Rows are independent — a failed row never hides or undoes a saved one.
 */
final readonly class EnterSessionGradesResult extends ApplicationResult
{
    /**
     * @param  list<array{exam_enrollment_id: int, status: string, grade_id: ?int}>  $outcomes
     */
    private function __construct(
        bool $success,
        public array $outcomes,
        public int $saved,
        public int $failed,
    ) {
        parent::__construct($success);
    }

    /**
     * @param  list<array{exam_enrollment_id: int, status: string, grade_id: ?int}>  $outcomes
     */
    public static function fromOutcomes(array $outcomes): self
    {
        $saved = count(array_filter($outcomes, static fn (array $o): bool => in_array($o['status'], ['saved', 'replayed'], true)));
        $failed = count($outcomes) - $saved;

        return new self($failed === 0, $outcomes, $saved, $failed);
    }
}
