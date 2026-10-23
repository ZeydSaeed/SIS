<?php

namespace App\Application\Results\Results;

use App\Application\Shared\Results\ApplicationResult;

/**
 * Per-student outcome of the term-results and annual / GPA sheets: `calculated` / `finalized`, `replayed` (same request seen before),
 * `already_official` (finalize skips a student who already has a current official result — re-finalizing is the
 * separately authorized rebuild) or an error code of the per-student command. Students are independent.
 */
readonly class ProcessTermResultsBatchResult extends ApplicationResult
{
    private const OK = ['calculated', 'finalized', 'replayed', 'already_official'];

    /**
     * @param  list<array{enrollment_id: int, status: string}>  $outcomes
     * @param  array<string, int>  $counts
     */
    final protected function __construct(bool $success, public array $outcomes, public array $counts, public int $failed)
    {
        parent::__construct($success);
    }

    /**
     * @param  list<array{enrollment_id: int, status: string}>  $outcomes
     */
    public static function fromOutcomes(array $outcomes): static
    {
        $counts = [];
        foreach ($outcomes as $outcome) {
            $counts[$outcome['status']] = ($counts[$outcome['status']] ?? 0) + 1;
        }
        $failed = count(array_filter($outcomes, static fn (array $o): bool => ! in_array($o['status'], self::OK, true)));

        return new static($failed === 0, $outcomes, $counts, $failed);
    }
}
