<?php

namespace App\Application\Leave\Commands;

use App\Application\Contracts\Command;
use App\Domain\Leave\Data\LeavePolicyTerms;
use App\Domain\Leave\Data\LeaveTypeFields;

/**
 * Creates a leave type with its first policy version (typeId null — code and terms required) or edits the type's
 * descriptive fields. Rules change only by publishing a new policy version.
 */
final readonly class SaveLeaveTypeCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public ?int $typeId,
        public ?string $code,
        public LeaveTypeFields $fields,
        public ?LeavePolicyTerms $terms,
        public int $actorUserId,
        public ?string $idempotencyKey,
    ) {}
}
