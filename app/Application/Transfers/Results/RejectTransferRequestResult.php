<?php

namespace App\Application\Transfers\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class RejectTransferRequestResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $transferRequestId = null,
        array $errors = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, [], $fromIdempotencyCache);
    }

    public static function success(int $transferRequestId): self
    {
        return new self(true, $transferRequestId);
    }

    public static function fromIdempotency(int $transferRequestId): self
    {
        return new self(true, $transferRequestId, fromIdempotencyCache: true);
    }

    /** @param  list<string>  $errors */
    public static function failure(array $errors): self
    {
        return new self(false, null, $errors);
    }
}
