<?php

namespace App\Application\Finance\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class RestorePaymentResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $paymentId = null,
        public ?int $studentFeeStatus = null,
        array $errors = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, [], $fromIdempotencyCache);
    }

    public static function success(int $paymentId, int $studentFeeStatus): self
    {
        return new self(true, $paymentId, $studentFeeStatus);
    }

    public static function fromIdempotency(int $paymentId, int $studentFeeStatus): self
    {
        return new self(true, $paymentId, $studentFeeStatus, fromIdempotencyCache: true);
    }

    /** @param  list<string>  $errors */
    public static function failure(array $errors): self
    {
        return new self(false, null, null, $errors);
    }
}
