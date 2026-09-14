<?php

namespace App\Application\Enrollment\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class ReopenEnrollmentResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $enrollmentId = null,
        public ?int $status = null,
        array $errors = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, [], $fromIdempotencyCache);
    }

    public static function success(int $enrollmentId, int $status): self
    {
        return new self(true, $enrollmentId, $status);
    }

    public static function fromIdempotency(int $enrollmentId, int $status): self
    {
        return new self(true, $enrollmentId, $status, fromIdempotencyCache: true);
    }

    /** @param  list<string>  $errors */
    public static function failure(array $errors): self
    {
        return new self(false, null, null, $errors);
    }
}
