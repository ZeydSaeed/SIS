<?php

namespace App\Application\Timetable\Support;

use App\Domain\Timetable\Data\TimetableBoard;
use App\Domain\Timetable\Repositories\PeriodRepositoryInterface;
use App\Domain\Timetable\Repositories\TimetableEngineReadRepositoryInterface;
use App\Domain\Timetable\Repositories\TimetableWorkspaceReadRepositoryInterface;

/**
 * Loads one school-year timetable as a {@see TimetableBoard} — the single projection every timetable service
 * reads. A section's requirements are the active teaching assignments naming the section, or its class without
 * a section (weekly = curriculum hours); the engine side adds settings, activities, groups, availability, rules
 * (effective today), rooms, workshops and per-section context.
 */
final class TimetableBoardLoader
{
    public function __construct(
        private readonly PeriodRepositoryInterface $periods,
        private readonly TimetableWorkspaceReadRepositoryInterface $workspace,
        private readonly TimetableEngineReadRepositoryInterface $engine,
    ) {}

    /**
     * @param  list<array{teacher_id: int, subject_id: int, class_id: int, section_id: int|null, weekly_hours: int|null}>|null  $lessons  already loaded lessons (avoids a second read)
     * @param  list<array<string, mixed>>|null  $schedules
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

        $sections = $this->workspace->sections($schoolId, $academicYearId);

        return new TimetableBoard(
            periods: $this->periods->listForSchool($schoolId),
            schedules: $schedules,
            requirements: self::requirements($sections, $lessons),
            teacherSubjects: $teacherSubjects,
            activeTeacherIds: array_column($teachers, 'id'),
            practicalSubjectIds: $this->workspace->practicalSubjectIds(),
            sectionIds: array_column($sections, 'id'),
            settings: $this->engine->settings($schoolId, $academicYearId),
            activities: $this->engine->activities($schoolId, $academicYearId),
            groups: $this->engine->groups($schoolId, $academicYearId),
            availability: $this->engine->availability($schoolId, $academicYearId),
            rules: $this->engine->rules($schoolId, $academicYearId, (new \DateTimeImmutable)->format('Y-m-d')),
            rooms: $this->engine->rooms($schoolId),
            workshops: $this->engine->workshops($schoolId),
            sectionInfo: $this->engine->sectionInfo($schoolId, $academicYearId),
            teacherLimits: $this->workspace->teacherLimits($schoolId, $academicYearId),
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
