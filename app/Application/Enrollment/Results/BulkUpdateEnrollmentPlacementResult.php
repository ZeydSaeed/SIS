<?php

namespace App\Application\Enrollment\Results;

final readonly class BulkUpdateEnrollmentPlacementResult
{
    /**
     * @param  list<int>  $enrollmentIds
     * @param  list<int>  $skippedIds
     * @param  array<int, string>  $skipReasons
     */
    public function __construct(
        public array $enrollmentIds,
        public int $classId,
        public int $sectionId,
        public int $count,
        public bool $fromIdempotency = false,
        public array $skippedIds = [],
        public array $skipReasons = [],
    ) {}

    /**
     * @param  list<int>  $enrollmentIds
     * @param  list<int>  $skippedIds
     * @param  array<int, string>  $skipReasons
     */
    public static function success(
        array $enrollmentIds,
        int $classId,
        int $sectionId,
        array $skippedIds = [],
        array $skipReasons = [],
    ): self {
        return new self(
            $enrollmentIds,
            $classId,
            $sectionId,
            count($enrollmentIds),
            false,
            $skippedIds,
            $skipReasons,
        );
    }

    /**
     * @param  list<int>  $enrollmentIds
     */
    public static function fromIdempotency(array $enrollmentIds, int $classId, int $sectionId): self
    {
        return new self($enrollmentIds, $classId, $sectionId, count($enrollmentIds), true);
    }
}
