<?php

namespace App\Domain\Results\Events;

use App\Domain\Shared\DomainEvent;

final readonly class RankingSnapshotBuilt implements DomainEvent
{
    public function __construct(
        private int $rankingSnapshotId,
        private int $schoolId,
        private int $academicYearId,
        private int $classId,
        private int $snapshotVersion,
        private int $participantCount,
        private \DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'ranking_snapshot_id' => $this->rankingSnapshotId,
            'school_id' => $this->schoolId,
            'academic_year_id' => $this->academicYearId,
            'class_id' => $this->classId,
            'snapshot_version' => $this->snapshotVersion,
            'participant_count' => $this->participantCount,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
