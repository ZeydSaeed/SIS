<?php

namespace App\Application\Leave\Commands;

use App\Application\Contracts\Command;
use App\Domain\Leave\Data\LeaveDraft;
use App\Domain\Leave\ValueObjects\RequesterKind;

/**
 * Creates a draft (requestId null) or rewrites a draft / a request returned for more information. A draft holds
 * no time and no balance until it is submitted.
 */
final readonly class SaveLeaveRequestCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public ?int $requestId,
        public ?int $expectedVersion,
        public RequesterKind $kind,
        public int $personId,
        public LeaveDraft $draft,
        public int $actorUserId,
        public bool $canManage,
        public ?string $idempotencyKey,
    ) {}
}
