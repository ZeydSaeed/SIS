<?php

namespace App\Application\Exams\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class EnterStudentGradeResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $gradeId = null,
        public ?int $academicYearId = null,
        array $errors = [],
        array $warnings = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, $warnings, $fromIdempotencyCache);
    }

    public static function success(int $gradeId, int $academicYearId): self
    {
        return new self(true, $gradeId, $academicYearId);
    }

    public static function fromIdempotency(int $gradeId, int $academicYearId): self
    {
        return new self(true, $gradeId, $academicYearId, fromIdempotencyCache: true);
    }
}
