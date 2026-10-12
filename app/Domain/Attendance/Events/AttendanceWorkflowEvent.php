<?php

namespace App\Domain\Attendance\Events;

use App\Domain\Shared\DomainEvent;

/**
 * One outbox event for the attendance workflows (sheet submit / review, excuse decisions, staff attendance),
 * staged inside the writing transaction. The outbox listener turns it into an audit log row and in-app
 * notifications — after commit, so nothing is announced that did not happen.
 */
final readonly class AttendanceWorkflowEvent implements DomainEvent
{
    public const SHEET_SUBMITTED = 'sheet.submitted';

    public const SHEET_REVIEW_STARTED = 'sheet.review_started';

    public const SHEET_APPROVED = 'sheet.approved';

    public const SHEET_RETURNED = 'sheet.returned';

    public const SHEET_REJECTED = 'sheet.rejected';

    public const EXCUSE_SUBMITTED = 'excuse.submitted';

    public const EXCUSE_APPROVED = 'excuse.approved';

    public const EXCUSE_REJECTED = 'excuse.rejected';

    public const STAFF_RECORDED = 'staff.recorded';

    public const STAFF_CORRECTED = 'staff.corrected';

    public const POLICY_CHANGED = 'policy.changed';

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function __construct(
        public string $name,
        public int $schoolId,
        public int $academicYearId,
        public string $entityType,
        public int $entityId,
        public ?int $actorUserId,
        public array $before,
        public array $after,
        public \DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function payload(): array
    {
        return [
            'name' => $this->name,
            'school_id' => $this->schoolId,
            'academic_year_id' => $this->academicYearId,
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'actor_user_id' => $this->actorUserId,
            'before' => $this->before,
            'after' => $this->after,
            'occurred_at' => $this->occurredAt->format('Y-m-d\TH:i:s.uP'),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): self
    {
        return new self(
            (string) $payload['name'],
            (int) $payload['school_id'],
            (int) $payload['academic_year_id'],
            (string) $payload['entity_type'],
            (int) $payload['entity_id'],
            isset($payload['actor_user_id']) ? (int) $payload['actor_user_id'] : null,
            is_array($payload['before'] ?? null) ? $payload['before'] : [],
            is_array($payload['after'] ?? null) ? $payload['after'] : [],
            new \DateTimeImmutable((string) $payload['occurred_at']),
        );
    }
}
