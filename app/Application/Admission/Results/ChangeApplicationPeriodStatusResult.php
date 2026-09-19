<?php

namespace App\Application\Admission\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class ChangeApplicationPeriodStatusResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $periodId = null,
        public ?int $fromStatus = null,
        public ?int $toStatus = null,
        array $errors = [],
        array $warnings = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, $warnings, $fromIdempotencyCache);
    }

    public static function success(int $periodId, int $fromStatus, int $toStatus): self
    {
        return new self(true, $periodId, $fromStatus, $toStatus);
    }

    public static function fromIdempotency(int $periodId, int $fromStatus, int $toStatus): self
    {
        return new self(true, $periodId, $fromStatus, $toStatus, fromIdempotencyCache: true);
    }

    /**
     * @param  list<string>  $errors
     */
    public static function failure(array $errors): self
    {
        return new self(false, errors: $errors);
    }
}
