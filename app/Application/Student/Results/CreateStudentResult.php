<?php

namespace App\Application\Student\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class CreateStudentResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $studentId = null,
        public ?string $studentCode = null,
        array $errors = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, fromIdempotencyCache: $fromIdempotencyCache);
    }

    public static function success(int $studentId, string $studentCode): self
    {
        return new self(true, $studentId, $studentCode);
    }

    public static function fromIdempotency(int $studentId, string $studentCode): self
    {
        return new self(true, $studentId, $studentCode, fromIdempotencyCache: true);
    }
}
