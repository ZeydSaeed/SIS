<?php

namespace App\Domain\Timetable\Events;

use App\Domain\Shared\DomainEvent;

/**
 * A change in the timetable engine, staged through the outbox (audit, notifications, cache):
 * configuration_changed · generation_queued · generation_completed · generation_failed · generation_applied ·
 * schedules_locked · schedules_unlocked · version_created · version_submitted · version_decided ·
 * version_published · version_archived · version_restored.
 */
final readonly class TimetableEngineChanged implements DomainEvent
{
    /** @param  array<string, mixed>  $details */
    public function __construct(
        private string $action,
        private int $schoolId,
        private int $academicYearId,
        private ?int $subjectId,
        private ?int $actorUserId,
        private array $details,
        private \DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'action' => $this->action,
            'school_id' => $this->schoolId,
            'academic_year_id' => $this->academicYearId,
            'subject_id' => $this->subjectId,
            'actor_user_id' => $this->actorUserId,
            'details' => $this->details,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
