<?php

namespace App\Application\Leave\Commands;

use App\Application\Contracts\Command;

/**
 * Grants the type's annual entitlement (current policy) for the year to every staff member it applies to who has
 * none yet. Running it again adds nothing; a person's granted amount is changed by an adjustment.
 */
final readonly class GrantLeaveEntitlementsCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $typeId,
        public int $actorUserId,
        public ?string $idempotencyKey,
    ) {}
}
