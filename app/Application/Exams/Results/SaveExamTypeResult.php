<?php

namespace App\Application\Exams\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class SaveExamTypeResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $examTypeId = null,
        public bool $created = false,
        array $errors = [],
        array $warnings = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, $warnings, $fromIdempotencyCache);
    }

    public static function success(int $examTypeId, bool $created): self
    {
        return new self(true, $examTypeId, $created);
    }

    public static function fromIdempotency(int $examTypeId, bool $created): self
    {
        return new self(true, $examTypeId, $created, fromIdempotencyCache: true);
    }
}
