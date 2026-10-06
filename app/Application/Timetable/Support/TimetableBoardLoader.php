<?php

namespace App\Application\Timetable\Support;

use App\Domain\Timetable\Data\TimetableBoard;
use App\Domain\Timetable\Repositories\PeriodRepositoryInterface;
use App\Domain\Timetable\Repositories\TimetableWorkspaceReadRepositoryInterface;

/**
 * Loads one school-year timetable as a {@see TimetableBoard}. A section's lessons are the active
 * teaching assignments naming the section, or its class without a section (weekly = curriculum hours).
 */
final class TimetableBoardLoader
{
    public function __construct(
        private readonly PeriodRepositoryInterface $periods,
        private readonly TimetableWorkspaceReadRepositoryInterface $workspace,
    ) {}

    /**
     * @param  list<array{teacher_id: int, subject_id: int, class_id: int, section_id: int|null, weekly_hours: int|null}>|null  $lessons  already loaded lessons (avoids a second read)
     * @param  list<array{id: int, section_id: int, day_of_week: int, period_id: int, subject_id: int, teacher_id: int, room_id: int|null}>|null  $schedules
     * @param  list<array{id: int, full_name: string}>|null  $teachers
     */
    public function load(int $schoolId, int $academicYearId, ?array $lessons = null, ?array $schedules = null, ?array $teachers = null): TimetableBoard
    {
        $lessons ??= $this->workspace->lessons($schoolId, $academicYearId);
        $schedules ??= $this->workspace->activeSchedules($schoolId, $academicYearId);
        $teachers ??= $this->workspace->teachers($schoolId, $academicYearId);

        $teacherSubjects = [];
        foreach ($this->workspace->teacherSubjects($schoolId, $academicYearId) as $row) {
            $teacherSubjects[$row['teacher_id'].':'.$row['subject_id']] = true;
        }

        return new TimetableBoard(
            periods: $this->periods->listForSchool($schoolId),
            schedules: $schedules,
            requirements: self::requirements($this->workspace->sections($schoolId, $academicYearId), $lessons),
            teacherSubjects: $teacherSubjects,
            activeTeacherIds: array_column($teachers, 'id'),
            practicalSubjectIds: $this->workspace->practicalSubjectIds(),
        );
    }

    /**
     * @param  list<array{id: int, class_id: int}>  $sections
     * @param  list<array{teacher_id: int, subject_id: int, class_id: int, section_id: int|null, weekly_hours: int|null}>  $lessons
     * @return list<array{section_id: int, subject_id: int, teacher_id: int, weekly: int|null}>
     */
    private static function requirements(array $sections, array $lessons): array
    {
        $requirements = [];
        foreach ($sections as $section) {
            foreach ($lessons as $lesson) {
                if ($lesson['class_id'] !== $section['class_id'] || ($lesson['section_id'] !== null && $lesson['section_id'] !== $section['id'])) {
                    continue;
                }
                $key = $section['id'].':'.$lesson['subject_id'].':'.$lesson['teacher_id'];
                $requirements[$key] ??= ['section_id' => $section['id'], 'subject_id' => $lesson['subject_id'], 'teacher_id' => $lesson['teacher_id'], 'weekly' => $lesson['weekly_hours']];
            }
        }

        return array_values($requirements);
    }
}
