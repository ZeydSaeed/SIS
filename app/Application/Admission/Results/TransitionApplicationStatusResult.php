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
        /** Student created when the transition accepted (and converted) the application. */
        public ?int $convertedStudentId = null,
    ) {
        parent::__construct($success, $errors, $warnings, $fromIdempotencyCache);
    }

    public static function success(
        int $applicationId,
        int $fromStatus,
        int $toStatus,
        ?int $convertedStudentId = null,
    ): self {
        return new self(true, $applicationId, $fromStatus, $toStatus, convertedStudentId: $convertedStudentId);
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
