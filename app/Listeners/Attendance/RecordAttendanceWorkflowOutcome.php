<?php

namespace App\Listeners\Attendance;

use App\Application\Audit\Commands\RegisterAuditLogCommand;
use App\Application\Audit\Commands\RegisterAuditLogHandler;
use App\Application\Communication\Commands\QueueMessageCommand;
use App\Application\Communication\Commands\QueueMessageHandler;
use App\Domain\Attendance\Events\AttendanceWorkflowEvent;
use App\Domain\Communication\ValueObjects\NotificationChannel;
use App\Infrastructure\Attendance\AttendanceRecipientDirectory;
use App\Infrastructure\Events\AttendanceWorkflowBridgeEvent;
use App\Security\Authorization\Permission;
use App\Security\Context\SchoolContextScope;

/**
 * After commit (outbox): one business audit row with before/after values, then in-app notifications through the
 * existing Communication module (no parallel notification system). Both steps are idempotent per outbox event.
 */
final class RecordAttendanceWorkflowOutcome
{
    private const MESSAGES = [
        AttendanceWorkflowEvent::SHEET_SUBMITTED => ['كشف حضور بانتظار المراجعة', 'وصل كشف الحضور رقم :id للمراجعة.'],
        AttendanceWorkflowEvent::SHEET_APPROVED => ['اعتُمد كشف الحضور', 'تم اعتماد كشف الحضور رقم :id.'],
        AttendanceWorkflowEvent::SHEET_RETURNED => ['أُعيد كشف الحضور للتصحيح', 'أُعيد كشف الحضور رقم :id للتصحيح. السبب: :reason'],
        AttendanceWorkflowEvent::SHEET_REJECTED => ['رُفض كشف الحضور', 'رُفض كشف الحضور رقم :id. السبب: :reason'],
        AttendanceWorkflowEvent::EXCUSE_SUBMITTED => ['عذر غياب بانتظار المراجعة', 'وصل عذر غياب رقم :id للمراجعة.'],
        AttendanceWorkflowEvent::EXCUSE_APPROVED => ['اعتُمد عذر الغياب', 'تم اعتماد عذر الغياب رقم :id.'],
        AttendanceWorkflowEvent::EXCUSE_REJECTED => ['رُفض عذر الغياب', 'رُفض عذر الغياب رقم :id. السبب: :reason'],
        AttendanceWorkflowEvent::STAFF_CORRECTED => ['تصحيح سجل الدوام', 'تم تصحيح سجل دوامك ليوم :date.'],
    ];

    public function __construct(
        private readonly RegisterAuditLogHandler $audit,
        private readonly QueueMessageHandler $messages,
        private readonly AttendanceRecipientDirectory $recipients,
        private readonly SchoolContextScope $scope,
    ) {}

    public function handle(AttendanceWorkflowBridgeEvent $bridge): void
    {
        $event = $bridge->domainEvent;
        // One stamp per outbox event: two distinct outcomes for the same sheet never share an idempotency key.
        $stamp = $event->name.':'.$event->entityId.':'.$event->occurredAt->format('U.u').':'.substr(md5((string) json_encode($event->payload())), 0, 12);

        $this->scope->run($event->schoolId, function () use ($event, $stamp): void {
            $this->audit->handle(new RegisterAuditLogCommand(
                $event->schoolId, 'attendance.'.$event->name, $event->entityType, $event->entityId, $event->before, $event->after,
                $event->actorUserId, null, null, null, 'att-audit:'.$stamp,
            ));

            if (! isset(self::MESSAGES[$event->name])) {
                return;
            }
            [$subject, $body] = self::MESSAGES[$event->name];
            $body = strtr($body, [
                ':id' => (string) $event->entityId,
                ':reason' => (string) ($event->after['reason'] ?? $event->after['note'] ?? ''),
                ':date' => (string) ($event->after['work_date'] ?? ''),
            ]);
            foreach ($this->recipientsOf($event) as $userId) {
                $this->messages->handle(new QueueMessageCommand(
                    $event->schoolId, 'user', $userId, NotificationChannel::InApp, $body, $subject, null, 'att-msg:'.$stamp.':'.$userId,
                ));
            }
        });
    }

    /**
     * @return list<int>
     */
    private function recipientsOf(AttendanceWorkflowEvent $event): array
    {
        $users = match ($event->name) {
            AttendanceWorkflowEvent::SHEET_SUBMITTED => $this->recipients->usersWithPermission($event->schoolId, Permission::ATTENDANCE_SHEET_REVIEW),
            AttendanceWorkflowEvent::EXCUSE_SUBMITTED => $this->recipients->usersWithPermission($event->schoolId, Permission::ATTENDANCE_EXCUSE_REVIEW),
            AttendanceWorkflowEvent::SHEET_APPROVED, AttendanceWorkflowEvent::SHEET_RETURNED, AttendanceWorkflowEvent::SHEET_REJECTED => [
                isset($event->after['teacher_id']) ? $this->recipients->userOfTeacher((int) $event->after['teacher_id']) : null,
                $this->recipients->submitterOfSession($event->entityId),
            ],
            AttendanceWorkflowEvent::EXCUSE_APPROVED, AttendanceWorkflowEvent::EXCUSE_REJECTED => [isset($event->after['submitted_by']) ? (int) $event->after['submitted_by'] : null],
            AttendanceWorkflowEvent::STAFF_CORRECTED => [isset($event->after['teacher_id']) ? $this->recipients->userOfTeacher((int) $event->after['teacher_id']) : null],
            default => [],
        };

        // Never notify the actor about their own action.
        return array_values(array_unique(array_filter($users, static fn (?int $id): bool => $id !== null && $id !== $event->actorUserId)));
    }
}
