<?php

namespace App\Application\Workflow\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class CancelApprovalRequestResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $requestId = null,
        public ?int $status = null,
        array $errors = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, [], $fromIdempotencyCache);
    }

    public static function success(int $requestId, int $status): self
    {
        return new self(true, $requestId, $status);
    }

    public static function fromIdempotency(int $requestId, int $status): self
    {
        return new self(true, $requestId, $status, fromIdempotencyCache: true);
    }

    /** @param  list<string>  $errors */
    public static function failure(array $errors): self
    {
        return new self(false, null, null, $errors);
    }
}
