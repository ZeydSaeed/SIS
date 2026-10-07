<?php

namespace App\Application\Timetable\DTOs;

use App\Domain\Timetable\Data\TimetableBoard;
use App\Domain\Timetable\Services\TeacherWorkloadAnalyzer;
use App\Domain\Timetable\Services\TimetableAdvisor;
use App\Domain\Timetable\Services\TimetableQualityScorer;

/** «الجدول الدراسي» builder: the school day, the lessons to place, what is placed, what is wrong, readiness, workload and quality. */
final readonly class TimetableWorkspaceDTO
{
    /**
     * @param  list<PeriodDTO>  $periods
     * @param  list<array{teacher_id: int, teacher_name: string, subject_id: int, subject_name: string, class_id: int, section_id: int|null, weekly_hours: int|null}>  $lessons
     * @param  list<array{id: int, section_id: int, day_of_week: int, period_id: int, subject_id: int, teacher_id: int, room_id: int|null}>  $schedules
     * @param  list<array{id: int, full_name: string, short_name: string}>  $teachers
     * @param  list<array{teacher_id: int, subject_id: int}>  $teacherSubjects
     * @param  list<int>  $practicalSubjectIds
     * @param  list<array{severity: string, code: string, section_id: int|null, teacher_id: int|null, subject_id: int|null, day: int|null, period_id: int|null, schedule_ids: list<int>, count: int|null}>  $issues
     * @param  list<array{section_id: int, branch_id: int, department_id: int|null, students: int}>  $placements
     * @param  array<string, mixed>  $advice  «جاهزية الجدول» ({@see TimetableAdvisor})
     * @param  list<array<string, mixed>>  $workload  «عبء المدرسين» ({@see TeacherWorkloadAnalyzer})
     * @param  array<string, mixed>  $quality  «جودة الجدول» ({@see TimetableQualityScorer})
     */
    public function __construct(
        public array $periods,
        public array $lessons,
        public array $schedules,
        public array $teachers,
        public array $teacherSubjects = [],
        public array $practicalSubjectIds = [],
        public array $issues = [],
        public array $placements = [],
        public array $advice = [],
        public array $workload = [],
        public array $quality = [],
        /** The projection the workspace was computed from — reused by the engine query, never sent to the page. */
        public ?TimetableBoard $board = null,
    ) {}
}
