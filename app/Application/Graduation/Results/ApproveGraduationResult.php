<?php

namespace App\Application\Graduation\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class ApproveGraduationResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $approvalId = null,
        public ?int $completionOutcomeVersionId = null,
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, [], [], $fromIdempotencyCache);
    }

    public static function success(int $approvalId, int $versionId): self
    {
        return new self(true, $approvalId, $versionId);
    }

    public static function fromIdempotency(int $approvalId, int $versionId): self
    {
        return new self(true, $approvalId, $versionId, fromIdempotencyCache: true);
    }
}
