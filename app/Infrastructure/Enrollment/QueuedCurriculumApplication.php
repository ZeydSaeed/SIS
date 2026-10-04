<?php

namespace App\Infrastructure\Enrollment;

use App\Application\Enrollment\Contracts\CurriculumApplicationQueue;
use App\Infrastructure\Jobs\ApplyCurriculumToEnrollmentsJob;

final class QueuedCurriculumApplication implements CurriculumApplicationQueue
{
    public function queueForCurriculum(int $schoolId, int $curriculumId): void
    {
        ApplyCurriculumToEnrollmentsJob::dispatch($schoolId, $curriculumId);
    }
}
