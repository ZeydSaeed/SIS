<?php

namespace App\Infrastructure\Persistence\Outbox;

use App\Application\Contracts\OutboxRepository;
use App\Domain\Enrollment\Events\EnrollmentCancelled;
use App\Domain\Enrollment\Events\EnrollmentPlacementUpdated;
use App\Domain\Enrollment\Events\StudentEnrolled;
use App\Domain\Shared\DomainEvent;
use App\Infrastructure\Persistence\Eloquent\OutboxMessageRecord;
use App\Intelligence\Support\CorrelationContext;

final class EloquentOutboxRepository implements OutboxRepository
{
    public function stage(DomainEvent $event, ?string $correlationId = null): void
    {
        OutboxMessageRecord::query()->create([
            'event_type' => $event::class,
            'payload' => $event->payload(),
            'correlation_id' => $correlationId ?? CorrelationContext::id(),
            'occurred_at' => $event->occurredAt(),
            'created_at' => now(),
        ]);
    }

    /**
     * @return list<OutboxMessageRecord>
     */
    public function fetchUnprocessed(int $limit = 50): array
    {
        return OutboxMessageRecord::query()
            ->whereNull('processed_at')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->all();
    }

    public function markProcessed(int $id): void
    {
        OutboxMessageRecord::query()
            ->whereKey($id)
            ->update(['processed_at' => now()]);
    }

    public function incrementAttempts(int $id): void
    {
        OutboxMessageRecord::query()
            ->whereKey($id)
            ->increment('attempts');
    }

    public function rehydrateEvent(string $eventType, array $payload): ?DomainEvent
    {
        return match ($eventType) {
            StudentEnrolled::class => new StudentEnrolled(
                enrollmentId: (int) $payload['enrollment_id'],
                studentId: (int) $payload['student_id'],
                schoolId: (int) $payload['school_id'],
                academicYearId: (int) $payload['academic_year_id'],
                classId: (int) $payload['class_id'],
                sectionId: (int) $payload['section_id'],
                enrollmentNumber: (string) $payload['enrollment_number'],
                enrolledBy: isset($payload['enrolled_by']) ? (int) $payload['enrolled_by'] : null,
                occurredAt: new \DateTimeImmutable((string) $payload['occurred_at']),
            ),
            EnrollmentCancelled::class => new EnrollmentCancelled(
                enrollmentId: (int) $payload['enrollment_id'],
                studentId: (int) $payload['student_id'],
                schoolId: (int) $payload['school_id'],
                academicYearId: (int) $payload['academic_year_id'],
                effectiveTo: (string) $payload['effective_to'],
                cancelledBy: isset($payload['cancelled_by']) ? (int) $payload['cancelled_by'] : null,
                occurredAt: new \DateTimeImmutable((string) $payload['occurred_at']),
            ),
            EnrollmentPlacementUpdated::class => new EnrollmentPlacementUpdated(
                enrollmentId: (int) $payload['enrollment_id'],
                studentId: (int) $payload['student_id'],
                schoolId: (int) $payload['school_id'],
                academicYearId: (int) $payload['academic_year_id'],
                previousClassId: (int) $payload['previous_class_id'],
                previousSectionId: (int) $payload['previous_section_id'],
                classId: (int) $payload['class_id'],
                sectionId: (int) $payload['section_id'],
                specializationId: isset($payload['specialization_id']) ? (int) $payload['specialization_id'] : null,
                updatedBy: isset($payload['updated_by']) ? (int) $payload['updated_by'] : null,
                occurredAt: new \DateTimeImmutable((string) $payload['occurred_at']),
            ),
            default => null,
        };
    }
}
