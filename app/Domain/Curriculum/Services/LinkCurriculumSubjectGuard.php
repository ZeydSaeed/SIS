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
        $curriculum = $this->curricula->findActiveInSchool($schoolId, $curriculumId);
        if ($curriculum === null) {
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
        // Linking it again used to answer «done» while keeping the old hours: the link is changed from its own row.
        // (An inactive link is reactivated by linking — that path stays.)
        $activeLinks = $this->curricula->listActiveLinks($schoolId, $curriculumId);
        foreach ($activeLinks as $link) {
            if ($link->subjectId === $subjectId) {
                return 'curriculum.subject_already_linked';
            }
        }
        // «عدد المواد» is the ceiling: one more subject needs a larger count first.
        if ($curriculum->plannedSubjectCount !== null && count($activeLinks) >= $curriculum->plannedSubjectCount) {
            return 'curriculum.subjects_exceed_planned';
        }

        return null;
    }

    /** «نظري» / «عملي» hours: each 0–40, and together at most 40. */
    public function hoursSplitError(?int $theoryHours, ?int $practicalHours): ?string
    {
        foreach ([$theoryHours, $practicalHours] as $hours) {
            if ($hours !== null && ($hours < 0 || $hours > 40)) {
                return 'curriculum.weekly_hours_invalid';
            }
        }

        return ($theoryHours ?? 0) + ($practicalHours ?? 0) > 40 ? 'curriculum.weekly_hours_invalid' : null;
    }
}
