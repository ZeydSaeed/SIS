<?php

namespace App\Application\Academic\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class CreateAcademicYearResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $academicYearId = null,
        public ?string $code = null,
        array $errors = [],
        array $warnings = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, $warnings, $fromIdempotencyCache);
    }

    public static function success(int $academicYearId, string $code): self
    {
        return new self(true, $academicYearId, $code);
    }

    public static function fromIdempotency(int $academicYearId, string $code): self
    {
        return new self(true, $academicYearId, $code, fromIdempotencyCache: true);
    }
}
