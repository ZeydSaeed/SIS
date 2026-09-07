<?php

namespace App\Listeners\Enrollment;

use App\Infrastructure\Events\EnrollmentCancelledBridgeEvent;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;

final class RecordEnrollmentCancelledAudit
{
    public function __construct(
        private readonly SecurityAuditLoggerInterface $securityAudit,
    ) {}

    public function handle(EnrollmentCancelledBridgeEvent $event): void
    {
        $payload = $event->domainEvent->payload();

        $this->securityAudit->record(
            SecurityEventType::EnrollmentDataModified,
            'enrollment.cancelled',
            'cancelled',
            null,
            'enrollment:'.($payload['enrollment_id'] ?? 'unknown'),
            [
                'student_id' => $payload['student_id'] ?? null,
                'school_id' => $payload['school_id'] ?? null,
                'academic_year_id' => $payload['academic_year_id'] ?? null,
                'effective_to' => $payload['effective_to'] ?? null,
            ],
        );
    }
}
