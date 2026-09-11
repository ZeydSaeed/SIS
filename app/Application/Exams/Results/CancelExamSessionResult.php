<?php

namespace App\Application\Exams\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class CancelExamSessionResult extends ApplicationResult
{
    /**
     * @param  list<int>  $withdrawnEnrollmentIds
     */
    private function __construct(
        bool $success,
        public ?int $examSessionId = null,
        public ?int $examId = null,
        public ?int $status = null,
        public array $withdrawnEnrollmentIds = [],
        array $errors = [],
        array $warnings = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, $warnings, $fromIdempotencyCache);
    }

    /**
     * @param  list<int>  $withdrawnEnrollmentIds
     */
    public static function success(
        int $examSessionId,
        int $examId,
        int $status,
        array $withdrawnEnrollmentIds,
    ): self {
        return new self(true, $examSessionId, $examId, $status, $withdrawnEnrollmentIds);
    }

    /**
     * @param  list<int>  $withdrawnEnrollmentIds
     */
    public static function fromIdempotency(
        int $examSessionId,
        int $examId,
        int $status,
        array $withdrawnEnrollmentIds,
    ): self {
        return new self(
            true,
            $examSessionId,
            $examId,
            $status,
            $withdrawnEnrollmentIds,
            fromIdempotencyCache: true,
        );
    }
}
