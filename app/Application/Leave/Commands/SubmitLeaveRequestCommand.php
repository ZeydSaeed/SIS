<?php

namespace App\Application\Leave\Commands;

use App\Application\Contracts\Command;

/**
 * Sends a draft (or a request returned for more information) for approval. The duration is counted again against
 * today's calendar and the type's current policy version is frozen on the request.
 */
final readonly class SubmitLeaveRequestCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $requestId,
        public int $expectedVersion,
        public int $actorUserId,
        public bool $canManage,
        public ?string $idempotencyKey,
    ) {}
}
