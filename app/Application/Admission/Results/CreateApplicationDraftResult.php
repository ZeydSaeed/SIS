<?php

namespace App\Application\Admission\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class CreateApplicationDraftResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $applicationId = null,
        public ?string $applicationNumber = null,
        array $errors = [],
        array $warnings = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, $warnings, $fromIdempotencyCache);
    }

    public static function success(int $applicationId, string $applicationNumber): self
    {
        return new self(true, $applicationId, $applicationNumber);
    }

    public static function fromIdempotency(int $applicationId, string $applicationNumber): self
    {
        return new self(true, $applicationId, $applicationNumber, fromIdempotencyCache: true);
    }

    /**
     * @param  list<string>  $errors
     */
    public static function failure(array $errors): self
    {
        return new self(false, errors: $errors);
    }
}
