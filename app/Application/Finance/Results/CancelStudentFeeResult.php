<?php

namespace App\Application\Finance\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class CancelStudentFeeResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $studentFeeId = null,
        array $errors = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, [], $fromIdempotencyCache);
    }

    public static function success(int $studentFeeId): self
    {
        return new self(true, $studentFeeId);
    }

    public static function fromIdempotency(int $studentFeeId): self
    {
        return new self(true, $studentFeeId, fromIdempotencyCache: true);
    }

    /** @param  list<string>  $errors */
    public static function failure(array $errors): self
    {
        return new self(false, null, $errors);
    }
}
