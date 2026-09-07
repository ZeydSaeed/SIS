<?php

namespace App\Application\Enrollment\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class UpdateEnrollmentPlacementResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $enrollmentId = null,
        public ?int $classId = null,
        public ?int $sectionId = null,
        array $errors = [],
        array $warnings = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, $warnings, $fromIdempotencyCache);
    }

    public static function success(int $enrollmentId, int $classId, int $sectionId): self
    {
        return new self(true, $enrollmentId, $classId, $sectionId);
    }

    public static function fromIdempotency(int $enrollmentId, int $classId, int $sectionId): self
    {
        return new self(true, $enrollmentId, $classId, $sectionId, fromIdempotencyCache: true);
    }
}
