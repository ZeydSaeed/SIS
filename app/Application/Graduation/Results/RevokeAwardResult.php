<?php

namespace App\Application\Graduation\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class RevokeAwardResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $revocationId = null,
        public ?int $awardVersionId = null,
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, [], [], $fromIdempotencyCache);
    }

    public static function success(int $revocationId, int $awardVersionId): self
    {
        return new self(true, $revocationId, $awardVersionId);
    }

    public static function fromIdempotency(int $revocationId, int $awardVersionId): self
    {
        return new self(true, $revocationId, $awardVersionId, fromIdempotencyCache: true);
    }
}
