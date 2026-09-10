<?php

namespace App\Application\Exams\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class CorrectStudentGradeResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $previousGradeId = null,
        public ?int $newGradeId = null,
        public ?int $academicYearId = null,
        array $errors = [],
        array $warnings = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, $warnings, $fromIdempotencyCache);
    }

    public static function success(int $previousGradeId, int $newGradeId, int $academicYearId): self
    {
        return new self(true, $previousGradeId, $newGradeId, $academicYearId);
    }

    public static function fromIdempotency(int $previousGradeId, int $newGradeId, int $academicYearId): self
    {
        return new self(true, $previousGradeId, $newGradeId, $academicYearId, fromIdempotencyCache: true);
    }
}
