<?php

namespace App\Domain\Attendance\Data;

/** A rule hit waiting for human review — never a verdict about a student or teacher. */
final readonly class AlertCandidate
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public string $ruleCode,
        public int $severity,
        public string $subjectType,
        public int $subjectId,
        public float $metric,
        public float $threshold,
        public array $details = [],
    ) {}
}
