<?php

namespace App\Domain\Leave\Data;

/** The fields of a request as entered (before the duration is computed and the rules are checked). */
final readonly class LeaveDraft
{
    public function __construct(
        public int $leaveTypeId,
        public string $dateFrom,
        public string $dateTo,
        public ?float $hours,
        public string $reason,
    ) {}
}
