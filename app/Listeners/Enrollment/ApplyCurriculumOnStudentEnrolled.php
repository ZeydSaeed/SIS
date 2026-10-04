<?php

namespace App\Listeners\Enrollment;

use App\Application\Enrollment\Commands\ApplyCurriculumToEnrollmentCommand;
use App\Application\Enrollment\Commands\ApplyCurriculumToEnrollmentHandler;
use App\Infrastructure\Events\StudentEnrolledBridgeEvent;
use App\Security\Context\SchoolContextScope;
use Illuminate\Support\Facades\Log;

/**
 * New enrollment → assign the required subjects of its governing curriculum.
 * Runs from the outbox (queued). Best-effort: a failure is logged and never
 * blocks other outbox listeners; staff can re-apply from the curriculum page.
 */
final class ApplyCurriculumOnStudentEnrolled
{
    public function __construct(
        private readonly SchoolContextScope $scope,
        private readonly ApplyCurriculumToEnrollmentHandler $handler,
    ) {}

    public function handle(StudentEnrolledBridgeEvent $event): void
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
            Log::error('enrollment.apply_curriculum_failed', [
                'enrollment_id' => $enrollmentId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
