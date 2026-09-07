<?php

namespace App\Listeners\Enrollment;

use App\Infrastructure\Events\EnrollmentPlacementUpdatedBridgeEvent;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;

final class RecordEnrollmentPlacementUpdatedAudit
{
    public function __construct(
        private readonly SecurityAuditLoggerInterface $securityAudit,
    ) {}

    public function handle(EnrollmentPlacementUpdatedBridgeEvent $event): void
    {
        $payload = $event->domainEvent->payload();

        $this->securityAudit->record(
            SecurityEventType::EnrollmentDataModified,
            'enrollment.placement_updated',
            'updated',
            null,
            'enrollment:'.($payload['enrollment_id'] ?? 'unknown'),
            [
                'student_id' => $payload['student_id'] ?? null,
                'school_id' => $payload['school_id'] ?? null,
                'previous_class_id' => $payload['previous_class_id'] ?? null,
                'previous_section_id' => $payload['previous_section_id'] ?? null,
                'class_id' => $payload['class_id'] ?? null,
                'section_id' => $payload['section_id'] ?? null,
            ],
        );
    }
}
