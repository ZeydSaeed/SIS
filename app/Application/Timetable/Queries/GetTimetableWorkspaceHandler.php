<?php

namespace App\Application\Timetable\Queries;

use App\Application\Timetable\DTOs\PeriodDTO;
use App\Application\Timetable\DTOs\TimetableWorkspaceDTO;
use App\Application\Timetable\Support\TimetableBoardLoader;
use App\Domain\Timetable\Data\PeriodSnapshot;
use App\Domain\Timetable\Repositories\TimetableWorkspaceReadRepositoryInterface;
use App\Domain\Timetable\Services\TimetableAuditor;

final class GetTimetableWorkspaceHandler
{
    public function __construct(
        private readonly TimetableWorkspaceReadRepositoryInterface $workspace,
        private readonly TimetableBoardLoader $boards,
        private readonly TimetableAuditor $auditor,
    ) {}

    public function handle(GetTimetableWorkspaceQuery $query): TimetableWorkspaceDTO
    {
        $lessons = $this->workspace->lessons($query->schoolId, $query->academicYearId);
        $schedules = $this->workspace->activeSchedules($query->schoolId, $query->academicYearId);
        $teachers = $this->workspace->teachers($query->schoolId, $query->academicYearId);
        $board = $this->boards->load($query->schoolId, $query->academicYearId, $lessons, $schedules, $teachers);

        return new TimetableWorkspaceDTO(
            periods: array_map(
                static fn (PeriodSnapshot $p): PeriodDTO => new PeriodDTO(
                    id: $p->id,
                    schoolId: $p->schoolId,
                    periodNumber: $p->periodNumber,
                    startTime: $p->startTime,
                    endTime: $p->endTime,
                    periodType: $p->periodType,
                ),
                $board->periods,
            ),
            lessons: $lessons,
            schedules: $schedules,
            teachers: $teachers,
            teacherSubjects: array_map(static function (string $key): array {
                [$teacherId, $subjectId] = explode(':', $key);

                return ['teacher_id' => (int) $teacherId, 'subject_id' => (int) $subjectId];
            }, array_keys($board->teacherSubjects)),
            practicalSubjectIds: $board->practicalSubjectIds,
            issues: $this->auditor->audit($board),
            placements: $this->workspace->sectionPlacements($query->schoolId, $query->academicYearId),
        );
    }
}
