<?php

namespace App\Application\Admission\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class OpenApplicationPeriodResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $periodId = null,
        array $errors = [],
        array $warnings = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, $warnings, $fromIdempotencyCache);
    }

    public static function success(int $periodId): self
    {
        return new self(true, $periodId);
    }

    public static function fromIdempotency(int $periodId): self
    {
        return new self(true, $periodId, fromIdempotencyCache: true);
    }

    /**
     * @param  list<string>  $errors
     */
    public static function failure(array $errors): self
    {
        return new self(false, errors: $errors);
    }
}
