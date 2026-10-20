<?php

namespace App\Application\Leave\Commands;

use App\Application\Contracts\Command;

/**
 * Carries the unused balance of a type from an earlier academic year into `$toYearId` for the people it applies to,
 * at most `$cap` per person (null = all of it). Each person is carried once per source year.
 */
final readonly class CarryOverLeaveBalancesCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $fromYearId,
        public int $toYearId,
        public int $typeId,
        public ?float $cap,
        public int $actorUserId,
        public ?string $idempotencyKey,
    ) {}
}
