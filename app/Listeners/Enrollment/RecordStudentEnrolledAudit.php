<?php

namespace App\Listeners\Enrollment;

use App\Infrastructure\Events\StudentEnrolledBridgeEvent;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;

final class RecordStudentEnrolledAudit
{
    public function __construct(
        private readonly SecurityAuditLoggerInterface $securityAudit,
    ) {}

    public function handle(StudentEnrolledBridgeEvent $event): void
    {
        $payload = $event->domainEvent->payload();

        $this->securityAudit->record(
            SecurityEventType::EnrollmentDataModified,
            'enrollment.student_enrolled',
            'created',
            null,
            'enrollment:'.($payload['enrollment_id'] ?? 'unknown'),
            [
                'student_id' => $payload['student_id'] ?? null,
                'school_id' => $payload['school_id'] ?? null,
                'academic_year_id' => $payload['academic_year_id'] ?? null,
            ],
        );
    }
}
