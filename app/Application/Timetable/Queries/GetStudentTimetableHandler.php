<?php

namespace App\Application\Timetable\Queries;

use App\Application\Timetable\DTOs\TimetableLessonsDTO;
use App\Domain\Timetable\Repositories\TimetableEngineReadRepositoryInterface;
use App\Domain\Timetable\Repositories\TimetableVersionRepositoryInterface;
use App\Domain\Timetable\Repositories\TimetableWorkspaceReadRepositoryInterface;
use App\Domain\Timetable\Services\EffectiveVersionSelector;
use App\Domain\Timetable\Services\StudentTimetableFilter;

/**
 * A student's week, derived from membership (spec §98): the active enrollment's section, its whole-class
 * lessons and the lessons of the student's groups — from the version governing the date, else the working grid.
 */
final class GetStudentTimetableHandler
{
    public function __construct(
        private readonly TimetableEngineReadRepositoryInterface $engine,
        private readonly TimetableVersionRepositoryInterface $versions,
        private readonly TimetableWorkspaceReadRepositoryInterface $workspace,
        private readonly EffectiveVersionSelector $selector,
        private readonly StudentTimetableFilter $filter,
    ) {}

    public function handle(GetStudentTimetableQuery $query): ?TimetableLessonsDTO
    {
        $enrollment = $this->engine->activeEnrollmentOfStudent($query->schoolId, $query->academicYearId, $query->studentId);
        if ($enrollment === null) {
            return null;
        }
        $date = $query->date ?? (new \DateTimeImmutable)->format('Y-m-d');
        $versionId = $this->selector->select($this->versions->listForYear($query->schoolId, $query->academicYearId), $date);
        $lessons = $versionId !== null
            ? $this->versions->entries($query->schoolId, $versionId, $enrollment['section_id'])
            : array_values(array_filter($this->workspace->activeSchedules($query->schoolId, $query->academicYearId),
                static fn (array $s): bool => $s['section_id'] === $enrollment['section_id']));
        $groups = $this->engine->groups($query->schoolId, $query->academicYearId);
        $result = $this->filter->filter($lessons, $this->engine->groupsOfEnrollment($query->schoolId, $enrollment['id']),
            array_map(static fn (array $g): int => $g['division_id'], $groups));

        return new TimetableLessonsDTO($result['lessons'], [
            'student_id' => $query->studentId,
            'enrollment_id' => $enrollment['id'],
            'section_id' => $enrollment['section_id'],
            'version_id' => $versionId,
            'unassigned_divisions' => $result['unassigned_divisions'],
        ]);
    }
}
