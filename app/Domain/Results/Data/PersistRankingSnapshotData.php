<?php

namespace App\Domain\Results\Data;

final readonly class PersistRankingSnapshotData
{
    /**
     * @param  list<\App\Domain\Results\Data\RankedParticipant>  $entries
     * @param  array<string, mixed>  $policyPin
     */
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $classId,
        public int $snapshotVersion,
        public int $participantCount,
        public string $sourceFingerprint,
        public array $policyPin,
        public string $calculatedAt,
        public ?string $correlationId,
        public ?int $createdBy,
        public array $entries,
    ) {}
}
