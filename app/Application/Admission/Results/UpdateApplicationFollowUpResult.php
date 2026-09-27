<?php

namespace App\Application\Admission\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class UpdateApplicationFollowUpResult extends ApplicationResult
{
    /**
     * @param  list<int>  $applicationIds
     * @param  list<string>  $errors
     * @param  list<string>  $warnings
     */
    private function __construct(
        bool $success,
        public array $applicationIds = [],
        array $errors = [],
        array $warnings = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, $warnings, $fromIdempotencyCache);
    }

    /**
     * @param  list<int>  $applicationIds
     */
    public static function success(array $applicationIds): self
    {
        return new self(true, applicationIds: $applicationIds);
    }

    /**
     * @param  list<int>  $applicationIds
     */
    public static function fromIdempotency(array $applicationIds): self
    {
        return new self(true, applicationIds: $applicationIds, fromIdempotencyCache: true);
    }

    /**
     * @param  list<string>  $errors
     */
    public static function failure(array $errors): self
    {
        return new self(false, errors: $errors);
    }
}
