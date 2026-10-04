<?php

namespace App\Application\Enrollment\Contracts;

/**
 * Queues "apply curriculum" for every active enrollment the curriculum governs
 * (bulk work never runs inside the HTTP request).
 */
interface CurriculumApplicationQueue
{
    public function queueForCurriculum(int $schoolId, int $curriculumId): void;
}
