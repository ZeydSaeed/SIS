<?php

namespace App\Infrastructure\Jobs;

use App\Application\Enrollment\Commands\ApplyCurriculumToEnrollmentCommand;
use App\Application\Enrollment\Commands\ApplyCurriculumToEnrollmentHandler;
use App\Domain\Enrollment\Contracts\EnrollmentCurriculumPort;
use App\Security\Context\SchoolContextScope;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Assigns the curriculum's required subjects to every active enrollment it governs.
 */
final class ApplyCurriculumToEnrollmentsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $schoolId,
        public readonly int $curriculumId,
    ) {}

    public function handle(
        SchoolContextScope $scope,
        EnrollmentCurriculumPort $curriculum,
        ApplyCurriculumToEnrollmentHandler $handler,
    ): void {
        $scope->run($this->schoolId, function () use ($curriculum, $handler): void {
            $assigned = 0;
            $skipped = 0;

            foreach ($curriculum->enrollmentIdsGovernedBy($this->schoolId, $this->curriculumId) as $enrollmentId) {
                $result = $handler->handle(new ApplyCurriculumToEnrollmentCommand($this->schoolId, $enrollmentId));
                $assigned += $result->assignedCount;
                $skipped += count($result->warnings);
            }

            Log::info('curriculum.applied_to_enrollments', [
                'school_id' => $this->schoolId,
                'curriculum_id' => $this->curriculumId,
                'subjects_assigned' => $assigned,
                'subjects_skipped' => $skipped,
            ]);
        });
    }
}
