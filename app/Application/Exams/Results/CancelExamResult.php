<?php

namespace App\Application\Exams\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class CancelExamResult extends ApplicationResult
{
    /**
     * @param  list<int>  $cancelledSessionIds
     * @param  list<int>  $withdrawnEnrollmentIds
     */
    private function __construct(
        bool $success,
        public ?int $examId = null,
        public ?int $status = null,
        public array $cancelledSessionIds = [],
        public array $withdrawnEnrollmentIds = [],
        array $errors = [],
        array $warnings = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, $warnings, $fromIdempotencyCache);
    }

    /**
     * @param  list<int>  $cancelledSessionIds
     * @param  list<int>  $withdrawnEnrollmentIds
     */
    public static function success(
        int $examId,
        int $status,
        array $cancelledSessionIds,
        array $withdrawnEnrollmentIds,
    ): self {
        return new self(true, $examId, $status, $cancelledSessionIds, $withdrawnEnrollmentIds);
    }

    /**
     * @param  list<int>  $cancelledSessionIds
     * @param  list<int>  $withdrawnEnrollmentIds
     */
    public static function fromIdempotency(
        int $examId,
        int $status,
        array $cancelledSessionIds,
        array $withdrawnEnrollmentIds,
    ): self {
        return new self(
            true,
            $examId,
            $status,
            $cancelledSessionIds,
            $withdrawnEnrollmentIds,
            fromIdempotencyCache: true,
        );
    }
}
