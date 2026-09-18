<?php

namespace App\Application\Admission\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class ConvertApplicationToStudentResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $applicationId = null,
        public ?int $studentId = null,
        array $errors = [],
        array $warnings = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, $warnings, $fromIdempotencyCache);
    }

    public static function success(int $applicationId, int $studentId): self
    {
        return new self(true, $applicationId, $studentId);
    }

    public static function fromIdempotency(int $applicationId, int $studentId): self
    {
        return new self(true, $applicationId, $studentId, fromIdempotencyCache: true);
    }

    /**
     * @param  list<string>  $errors
     */
    public static function failure(array $errors): self
    {
        return new self(false, errors: $errors);
    }
}
