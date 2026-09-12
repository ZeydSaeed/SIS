<?php

namespace App\Application\Finance\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class CreateFeeTypeResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $feeTypeId = null,
        array $errors = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, [], $fromIdempotencyCache);
    }

    public static function success(int $feeTypeId): self
    {
        return new self(true, $feeTypeId);
    }

    public static function fromIdempotency(int $feeTypeId): self
    {
        return new self(true, $feeTypeId, fromIdempotencyCache: true);
    }

    /** @param  list<string>  $errors */
    public static function failure(array $errors): self
    {
        return new self(false, null, $errors);
    }
}
