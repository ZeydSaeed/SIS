<?php

namespace App\Application\Graduation\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class IssueAwardResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $awardId = null,
        public ?int $awardVersionId = null,
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, [], [], $fromIdempotencyCache);
    }

    public static function success(int $awardId, int $awardVersionId): self
    {
        return new self(true, $awardId, $awardVersionId);
    }

    public static function fromIdempotency(int $awardId, int $awardVersionId): self
    {
        return new self(true, $awardId, $awardVersionId, fromIdempotencyCache: true);
    }
}
