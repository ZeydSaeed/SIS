<?php

namespace App\Application\Hr\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class DeactivateJobPositionResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $jobPositionId = null,
        array $errors = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, [], $fromIdempotencyCache);
    }

    public static function success(int $jobPositionId): self
    {
        return new self(true, $jobPositionId);
    }

    public static function fromIdempotency(int $jobPositionId): self
    {
        return new self(true, $jobPositionId, fromIdempotencyCache: true);
    }

    /** @param  list<string>  $errors */
    public static function failure(array $errors): self
    {
        return new self(false, null, $errors);
    }
}
