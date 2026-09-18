<?php

namespace App\Application\Admission\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class TransitionApplicationStatusResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $applicationId = null,
        public ?int $fromStatus = null,
        public ?int $toStatus = null,
        array $errors = [],
        array $warnings = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, $warnings, $fromIdempotencyCache);
    }

    public static function success(int $applicationId, int $fromStatus, int $toStatus): self
    {
        return new self(true, $applicationId, $fromStatus, $toStatus);
    }

    public static function fromIdempotency(int $applicationId, int $fromStatus, int $toStatus): self
    {
        return new self(true, $applicationId, $fromStatus, $toStatus, fromIdempotencyCache: true);
    }

    /**
     * @param  list<string>  $errors
     */
    public static function failure(array $errors): self
    {
        return new self(false, errors: $errors);
    }
}
