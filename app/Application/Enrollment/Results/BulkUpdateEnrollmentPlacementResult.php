<?php

namespace App\Application\Enrollment\Results;

final readonly class BulkUpdateEnrollmentPlacementResult
{
    /**
     * @param  list<int>  $enrollmentIds
     */
    public function __construct(
        public array $enrollmentIds,
        public int $classId,
        public int $sectionId,
        public int $count,
        public bool $fromIdempotency = false,
    ) {}

    /**
     * @param  list<int>  $enrollmentIds
     */
    public static function success(array $enrollmentIds, int $classId, int $sectionId): self
    {
        return new self($enrollmentIds, $classId, $sectionId, count($enrollmentIds));
    }

    /**
     * @param  list<int>  $enrollmentIds
     */
    public static function fromIdempotency(array $enrollmentIds, int $classId, int $sectionId): self
    {
        return new self($enrollmentIds, $classId, $sectionId, count($enrollmentIds), true);
    }
}
