<?php

namespace App\Application\Transfers\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class CompleteTransferResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $transferRequestId = null,
        public ?int $transferRecordId = null,
        public ?int $toEnrollmentId = null,
        array $errors = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, [], $fromIdempotencyCache);
    }

    public static function success(int $transferRequestId, int $transferRecordId, int $toEnrollmentId): self
    {
        return new self(true, $transferRequestId, $transferRecordId, $toEnrollmentId);
    }

    public static function fromIdempotency(int $transferRequestId, int $transferRecordId, int $toEnrollmentId): self
    {
        return new self(true, $transferRequestId, $transferRecordId, $toEnrollmentId, fromIdempotencyCache: true);
    }

    /** @param  list<string>  $errors */
    public static function failure(array $errors): self
    {
        return new self(false, null, null, null, $errors);
    }
}
