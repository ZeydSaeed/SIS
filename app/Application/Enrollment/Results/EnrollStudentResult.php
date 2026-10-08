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

    /** @param  list<string>  $warnings  non-blocking notices, e.g. `enrollment.no_curriculum_for_placement` */
    public static function success(int $enrollmentId, string $enrollmentNumber, array $warnings = []): self
    {
        return new self(true, $enrollmentId, $enrollmentNumber, warnings: $warnings);
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
