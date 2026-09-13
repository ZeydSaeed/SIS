<?php

namespace App\Application\Audit\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class RecordLoginHistoryResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $loginHistoryId = null,
        array $errors = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, [], $fromIdempotencyCache);
    }

    public static function success(int $loginHistoryId): self
    {
        return new self(true, $loginHistoryId);
    }

    public static function fromIdempotency(int $loginHistoryId): self
    {
        return new self(true, $loginHistoryId, fromIdempotencyCache: true);
    }

    /** @param  list<string>  $errors */
    public static function failure(array $errors): self
    {
        return new self(false, null, $errors);
    }
}
