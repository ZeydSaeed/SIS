<?php

namespace App\Application\Leave\Commands;

use App\Application\Contracts\Command;
use App\Domain\Leave\ValueObjects\RequesterKind;

/** An audited balance correction (positive or negative) with its reason — a new ledger row, never an edit. */
final readonly class AdjustLeaveBalanceCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $typeId,
        public RequesterKind $kind,
        public int $personId,
        public float $quantity,
        public string $reason,
        public int $actorUserId,
        public ?string $idempotencyKey,
    ) {}
}
