<?php

namespace App\Application\Admission\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class UpdateApplicationDraftResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $applicationId = null,
        array $errors = [],
        array $warnings = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, $warnings, $fromIdempotencyCache);
    }

    public static function success(int $applicationId): self
    {
        return new self(true, $applicationId);
    }

    public static function fromIdempotency(int $applicationId): self
    {
        return new self(true, $applicationId, fromIdempotencyCache: true);
    }

    /**
     * @param  list<string>  $errors
     */
    public static function failure(array $errors): self
    {
        return new self(false, errors: $errors);
    }
}
