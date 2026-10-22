<?php

namespace App\Infrastructure\Exams;

use App\Application\Attendance\Contracts\TeacherDirectoryInterface;
use App\Application\Exams\Contracts\GradeEntryScopePort;
use App\Database\SchemaHelper;
use App\Domain\Exams\Data\GradeSeatPlacement;
use App\Domain\Exams\Data\TeachingScope;
use App\Domain\Exams\Services\GradeEntryCoverage;
use App\Domain\Teachers\ValueObjects\TeachingAssignmentStatus;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Grade-entry scope from teachers.teaching_assignments (active on today's date by the app clock). */
final class TeachingAssignmentGradeEntryScope implements GradeEntryScopePort
{
    public function __construct(
        private readonly TeacherDirectoryInterface $teachers,
    ) {}

    public function teacherCoversSeat(int $userId, int $schoolId, int $examEnrollmentId): bool
    {
        $seat = $this->placements($schoolId, static fn (Builder $q) => $q->where('ee.id', $examEnrollmentId))[0] ?? null;
        if ($seat === null) {
            return false;
        }

        return $this->covered($userId, $seat->academicYearId, $schoolId, [$seat]) !== [];
    }

    public function coveredSeatIds(int $userId, int $schoolId, int $examSessionId): array
    {
        $seats = $this->placements($schoolId, static fn (Builder $q) => $q->where('ee.exam_session_id', $examSessionId));
        if ($seats === []) {
            return [];
        }

        return array_map(static fn (GradeSeatPlacement $s): int => $s->examEnrollmentId, $this->covered($userId, $seats[0]->academicYearId, $schoolId, $seats));
    }

    public function coveredSessionIds(int $userId, int $schoolId, int $academicYearId): array
    {
        $teacherId = $this->teachers->teacherIdForUser($userId, $schoolId, $academicYearId);
        if ($teacherId === null) {
            return [];
        }

        $scopes = $this->scopes($teacherId, $schoolId, $academicYearId);
        $subjects = array_values(array_unique(array_map(static fn (TeachingScope $s): int => $s->subjectId, $scopes)));
        if ($subjects === []) {
            return [];
        }

        $sessions = [];
        foreach ($this->placements($schoolId, static fn (Builder $q) => $q->where('ex.academic_year_id', $academicYearId)->whereIn('es.subject_id', $subjects)) as $seat) {
            if (GradeEntryCoverage::anyCovers($seat, $scopes)) {
                $sessions[$seat->examSessionId] = true;
            }
        }

        return array_keys($sessions);
    }

    /**
     * @param  list<GradeSeatPlacement>  $seats  all of one academic year
     * @return list<GradeSeatPlacement>
     */
    private function covered(int $userId, int $academicYearId, int $schoolId, array $seats): array
    {
        $teacherId = $this->teachers->teacherIdForUser($userId, $schoolId, $academicYearId);
        if ($teacherId === null) {
            return [];
        }

        $scopes = $this->scopes($teacherId, $schoolId, $academicYearId);

        return array_values(array_filter($seats, static fn (GradeSeatPlacement $seat): bool => GradeEntryCoverage::anyCovers($seat, $scopes)));
    }

    /**
     * @param  callable(Builder): mixed  $filter
     * @return list<GradeSeatPlacement>
     */
    private function placements(int $schoolId, callable $filter): array
    {
        $query = DB::table(SchemaHelper::qualified('exams', 'exam_enrollments').' as ee')
            ->join(SchemaHelper::qualified('exams', 'exam_sessions').' as es', 'es.id', '=', 'ee.exam_session_id')
            ->join(SchemaHelper::qualified('exams', 'exams').' as ex', 'ex.id', '=', 'es.exam_id')
            ->join(SchemaHelper::qualified('enrollment', 'enrollments').' as en', 'en.id', '=', 'ee.enrollment_id')
            ->where('ee.school_id', $schoolId);
        $filter($query);

        return $query
            ->orderBy('ee.id')
            ->get([
                'ee.id', 'ee.exam_session_id', 'ee.school_id', 'ex.academic_year_id', 'es.subject_id',
                'en.branch_id', 'en.department_id', 'en.class_id', 'en.section_id',
            ])
            ->map(static fn (object $row): GradeSeatPlacement => new GradeSeatPlacement(
                examEnrollmentId: (int) $row->id,
                schoolId: (int) $row->school_id,
                academicYearId: (int) $row->academic_year_id,
                subjectId: (int) $row->subject_id,
                branchId: $row->branch_id !== null ? (int) $row->branch_id : null,
                departmentId: $row->department_id !== null ? (int) $row->department_id : null,
                classId: (int) $row->class_id,
                sectionId: (int) $row->section_id,
                examSessionId: (int) $row->exam_session_id,
            ))
            ->all();
    }

    /**
     * @return list<TeachingScope>
     */
    private function scopes(int $teacherId, int $schoolId, int $academicYearId): array
    {
        $today = now()->toDateString();
        $int = static fn (mixed $v): ?int => $v !== null ? (int) $v : null;

        return DB::table(SchemaHelper::qualified('teachers', 'teaching_assignments'))
            ->where('teacher_id', $teacherId)
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->where('status', TeachingAssignmentStatus::Active)
            ->where('effective_from', '<=', $today)
            ->where(static fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $today))
            ->get(['school_id', 'academic_year_id', 'subject_id', 'branch_id', 'department_id', 'class_id', 'section_id'])
            ->map(static fn (object $r): TeachingScope => new TeachingScope(
                schoolId: (int) $r->school_id,
                academicYearId: (int) $r->academic_year_id,
                subjectId: (int) $r->subject_id,
                branchId: (int) $r->branch_id,
                departmentId: $int($r->department_id),
                classId: $int($r->class_id),
                sectionId: $int($r->section_id),
            ))
            ->values()
            ->all();
    }
}
