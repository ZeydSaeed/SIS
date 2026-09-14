<?php

namespace App\Application\Communication\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class RequeueMessageResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $messageId = null,
        array $errors = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, [], $fromIdempotencyCache);
    }

    public static function success(int $messageId): self
    {
        return new self(true, $messageId);
    }

    public static function fromIdempotency(int $messageId): self
    {
        return new self(true, $messageId, fromIdempotencyCache: true);
    }

    /** @param  list<string>  $errors */
    public static function failure(array $errors): self
    {
        return new self(false, null, $errors);
    }
}
