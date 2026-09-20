<?php

namespace App\Application\Student\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class ChangeStudentStatusesResult extends ApplicationResult
{
    /**
     * @param  list<int>  $studentIds
     */
    private function __construct(
        bool $success,
        public array $studentIds = [],
        public ?int $status = null,
        public int $count = 0,
        array $errors = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, fromIdempotencyCache: $fromIdempotencyCache);
    }

    /**
     * @param  list<int>  $studentIds
     */
    public static function success(array $studentIds, int $status): self
    {
        return new self(true, $studentIds, $status, count($studentIds));
    }

    /**
     * @param  list<int>  $studentIds
     */
    public static function fromIdempotency(array $studentIds, int $status): self
    {
        return new self(true, $studentIds, $status, count($studentIds), fromIdempotencyCache: true);
    }
}
