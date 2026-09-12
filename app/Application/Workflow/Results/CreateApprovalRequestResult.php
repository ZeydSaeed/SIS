<?php

namespace App\Application\Workflow\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class CreateApprovalRequestResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $requestId = null,
        array $errors = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, [], $fromIdempotencyCache);
    }

    public static function success(int $requestId): self
    {
        return new self(true, $requestId);
    }

    public static function fromIdempotency(int $requestId): self
    {
        return new self(true, $requestId, fromIdempotencyCache: true);
    }

    /** @param  list<string>  $errors */
    public static function failure(array $errors): self
    {
        return new self(false, null, $errors);
    }
}
