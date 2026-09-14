<?php

namespace App\Application\Exams\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class ReopenExamEnrollmentResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $examEnrollmentId = null,
        public ?int $examSessionId = null,
        public ?int $enrollmentId = null,
        public ?int $status = null,
        array $errors = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, [], $fromIdempotencyCache);
    }

    public static function success(
        int $examEnrollmentId,
        int $examSessionId,
        int $enrollmentId,
        int $status,
    ): self {
        return new self(true, $examEnrollmentId, $examSessionId, $enrollmentId, $status);
    }

    public static function fromIdempotency(
        int $examEnrollmentId,
        int $examSessionId,
        int $enrollmentId,
        int $status,
    ): self {
        return new self(
            true,
            $examEnrollmentId,
            $examSessionId,
            $enrollmentId,
            $status,
            fromIdempotencyCache: true,
        );
    }

    /** @param  list<string>  $errors */
    public static function failure(array $errors): self
    {
        return new self(false, null, null, null, null, $errors);
    }
}
