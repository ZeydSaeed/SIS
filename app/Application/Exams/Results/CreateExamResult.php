<?php

namespace App\Application\Exams\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class CreateExamResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $examId = null,
        public ?int $academicYearId = null,
        public ?int $status = null,
        array $errors = [],
        array $warnings = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, $warnings, $fromIdempotencyCache);
    }

    public static function success(int $examId, int $academicYearId, int $status): self
    {
        return new self(true, $examId, $academicYearId, $status);
    }

    public static function fromIdempotency(int $examId, int $academicYearId, int $status): self
    {
        return new self(true, $examId, $academicYearId, $status, fromIdempotencyCache: true);
    }
}
