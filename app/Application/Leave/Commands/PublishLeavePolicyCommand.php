<?php

namespace App\Application\Leave\Commands;

use App\Application\Contracts\Command;
use App\Domain\Leave\Data\LeavePolicyTerms;

/**
 * Publishes the next version of a type's rules from today. Earlier versions stay as they were: requests already
 * submitted keep the version they were submitted under — nothing is recomputed.
 */
final readonly class PublishLeavePolicyCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $typeId,
        public LeavePolicyTerms $terms,
        public int $actorUserId,
        public ?string $idempotencyKey,
    ) {}
}
