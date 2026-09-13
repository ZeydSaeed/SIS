<?php

namespace App\Application\Hr\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class RegisterEmployeeResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $employeeId = null,
        array $errors = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, [], $fromIdempotencyCache);
    }

    public static function success(int $employeeId): self
    {
        return new self(true, $employeeId);
    }

    public static function fromIdempotency(int $employeeId): self
    {
        return new self(true, $employeeId, fromIdempotencyCache: true);
    }

    /** @param  list<string>  $errors */
    public static function failure(array $errors): self
    {
        return new self(false, null, $errors);
    }
}
