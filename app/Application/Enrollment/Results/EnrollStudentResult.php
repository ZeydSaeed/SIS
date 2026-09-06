<?php

namespace App\Application\Enrollment\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class EnrollStudentResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $enrollmentId = null,
        public ?string $enrollmentNumber = null,
        array $errors = [],
        array $warnings = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, $warnings, $fromIdempotencyCache);
    }

    public static function success(int $enrollmentId, string $enrollmentNumber): self
    {
        return new self(true, $enrollmentId, $enrollmentNumber);
    }

    public static function fromIdempotency(int $enrollmentId, string $enrollmentNumber): self
    {
        return new self(true, $enrollmentId, $enrollmentNumber, fromIdempotencyCache: true);
    }

    /**
     * @param  list<string>  $errors
     */
    public static function failure(array $errors): self
    {
        return new self(false, errors: $errors);
    }
}
