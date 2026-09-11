<?php

namespace App\Application\Exams\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class CloseExamSessionResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $examSessionId = null,
        public ?int $examId = null,
        public ?int $status = null,
        array $errors = [],
        array $warnings = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, $warnings, $fromIdempotencyCache);
    }

    public static function success(int $examSessionId, int $examId, int $status): self
    {
        return new self(true, $examSessionId, $examId, $status);
    }

    public static function fromIdempotency(int $examSessionId, int $examId, int $status): self
    {
        return new self(true, $examSessionId, $examId, $status, fromIdempotencyCache: true);
    }
}
