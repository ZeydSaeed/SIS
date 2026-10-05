<?php

namespace App\Listeners\Enrollment;

use App\Application\Enrollment\Commands\ApplyCurriculumToEnrollmentCommand;
use App\Application\Enrollment\Commands\ApplyCurriculumToEnrollmentHandler;
use App\Infrastructure\Events\EnrollmentPlacementUpdatedBridgeEvent;
use App\Security\Context\SchoolContextScope;
use Illuminate\Support\Facades\Log;

/**
 * Placement moved (enrollments page, or a branch / department change on the student
 * page) → assign the required subjects of the curriculum that now governs the active
 * enrollment. Additive and idempotent; runs from the outbox (queued). Best-effort,
 * like ApplyCurriculumOnStudentEnrolled.
 */
final class ApplyCurriculumOnEnrollmentPlacementUpdated
{
    public function __construct(
        private readonly SchoolContextScope $scope,
        private readonly ApplyCurriculumToEnrollmentHandler $handler,
    ) {}

    public function handle(EnrollmentPlacementUpdatedBridgeEvent $event): void
    {
        $payload = $event->domainEvent->payload();
        $schoolId = (int) ($payload['school_id'] ?? 0);
        $enrollmentId = (int) ($payload['enrollment_id'] ?? 0);
        if ($schoolId < 1 || $enrollmentId < 1) {
            return;
        }

        try {
            $this->scope->run($schoolId, fn () => $this->handler->handle(
                new ApplyCurriculumToEnrollmentCommand($schoolId, $enrollmentId),
            ));
        } catch (\Throwable $e) {
            Log::error('enrollment.apply_curriculum_after_placement_failed', [
                'enrollment_id' => $enrollmentId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
