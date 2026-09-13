<?php

namespace App\Domain\Curriculum\Services;

use App\Domain\Curriculum\Repositories\CurriculumRepositoryInterface;
use App\Domain\Curriculum\Repositories\SubjectRepositoryInterface;

final class LinkCurriculumSubjectGuard
{
    public function __construct(
        private readonly CurriculumRepositoryInterface $curricula,
        private readonly SubjectRepositoryInterface $subjects,
    ) {}

    public function rejectionCode(
        int $schoolId,
        int $curriculumId,
        int $subjectId,
        ?int $weeklyHours,
        int $subjectOrder,
    ): ?string {
        if ($this->curricula->findActiveInSchool($schoolId, $curriculumId) === null) {
            return 'curriculum.curriculum_not_found';
        }
        if ($this->subjects->findActive($subjectId) === null) {
            return 'curriculum.subject_not_found';
        }
        if ($weeklyHours !== null && ($weeklyHours < 0 || $weeklyHours > 40)) {
            return 'curriculum.weekly_hours_invalid';
        }
        if ($subjectOrder < 0 || $subjectOrder > 999) {
            return 'curriculum.subject_order_invalid';
        }

        return null;
    }
}
