<?php

namespace App\Domain\Exams\Services;

use App\Domain\Exams\Data\GradeSeatPlacement;
use App\Domain\Exams\Data\TeachingScope;

/**
 * A teacher may enter a grade only for a seat one of their teaching assignments covers: same school, academic year
 * and subject, same branch, and — where the assignment narrows it — the same department, class and section.
 * A seat without a branch is covered by no assignment (fail closed).
 */
final class GradeEntryCoverage
{
    /**
     * @param  list<TeachingScope>  $scopes
     */
    public static function anyCovers(GradeSeatPlacement $seat, array $scopes): bool
    {
        foreach ($scopes as $scope) {
            if (self::covers($seat, $scope)) {
                return true;
            }
        }

        return false;
    }

    public static function covers(GradeSeatPlacement $seat, TeachingScope $scope): bool
    {
        if ($seat->branchId === null) {
            return false;
        }

        return $scope->schoolId === $seat->schoolId
            && $scope->academicYearId === $seat->academicYearId
            && $scope->subjectId === $seat->subjectId
            && $scope->branchId === $seat->branchId
            && ($scope->departmentId === null || $scope->departmentId === $seat->departmentId)
            && ($scope->classId === null || $scope->classId === $seat->classId)
            && ($scope->sectionId === null || $scope->sectionId === $seat->sectionId);
    }
}
