<?php

namespace App\Application\Leave\Commands;

use App\Application\Contracts\Command;

/**
 * Attaches a file (medical report, order …) to a request through the Documents module. Allowed while the request
 * is a draft, pending, returned or approved; never after it is rejected, withdrawn or cancelled.
 */
final readonly class AttachLeaveDocumentCommand implements Command
{
    /**
     * @param  array{name: string, mime: string, contents: string}  $file
     */
    public function __construct(
        public int $schoolId,
        public int $requestId,
        public array $file,
        public int $actorUserId,
        public bool $canManage,
        public ?string $idempotencyKey,
    ) {}
}
