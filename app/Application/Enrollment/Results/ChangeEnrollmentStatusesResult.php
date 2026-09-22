<?php

namespace App\Application\Enrollment\Results;

final readonly class ChangeEnrollmentStatusesResult
{
    /**
     * @param  list<int>  $enrollmentIds
     */
    public function __construct(
        public array $enrollmentIds,
        public int $status,
        public int $count,
        public bool $fromIdempotency = false,
    ) {}

    /**
     * @param  list<int>  $enrollmentIds
     */
    public static function success(array $enrollmentIds, int $status): self
    {
        return new self($enrollmentIds, $status, count($enrollmentIds));
    }

    /**
     * @param  list<int>  $enrollmentIds
     */
    public static function fromIdempotency(array $enrollmentIds, int $status): self
    {
        return new self($enrollmentIds, $status, count($enrollmentIds), true);
    }
}
