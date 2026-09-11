<?php

namespace App\Application\Exams\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class CancelExamEnrollmentResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $examEnrollmentId = null,
        public ?int $examSessionId = null,
        public ?int $enrollmentId = null,
        public ?int $status = null,
        public bool $noop = false,
        array $errors = [],
        array $warnings = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, $warnings, $fromIdempotencyCache);
    }

    public static function success(
        int $examEnrollmentId,
        int $examSessionId,
        int $enrollmentId,
        int $status,
        bool $noop = false,
    ): self {
        return new self(true, $examEnrollmentId, $examSessionId, $enrollmentId, $status, $noop);
    }

    public static function fromIdempotency(
        int $examEnrollmentId,
        int $examSessionId,
        int $enrollmentId,
        int $status,
        bool $noop = false,
    ): self {
        return new self(
            true,
            $examEnrollmentId,
            $examSessionId,
            $enrollmentId,
            $status,
            $noop,
            fromIdempotencyCache: true,
        );
    }
}
