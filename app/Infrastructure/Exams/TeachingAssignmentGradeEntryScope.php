<?php

namespace App\Infrastructure\Exams;

use App\Application\Attendance\Contracts\TeacherDirectoryInterface;
use App\Application\Exams\Contracts\GradeEntryScopePort;
use App\Database\SchemaHelper;
use App\Domain\Exams\Data\GradeSeatPlacement;
use App\Domain\Exams\Data\TeachingScope;
use App\Domain\Exams\Services\GradeEntryCoverage;
use App\Domain\Teachers\ValueObjects\TeachingAssignmentStatus;
use Illuminate\Support\Facades\DB;

/** Grade-entry scope from teachers.teaching_assignments (active on today's date by the app clock). */
final class TeachingAssignmentGradeEntryScope implements GradeEntryScopePort
{
    public function __construct(
        private readonly TeacherDirectoryInterface $teachers,
    ) {}

    public function teacherCoversSeat(int $userId, int $schoolId, int $examEnrollmentId): bool
    {
        $seat = $this->placement($examEnrollmentId, $schoolId);
        if ($seat === null) {
            return false;
        }

        $teacherId = $this->teachers->teacherIdForUser($userId, $schoolId, $seat->academicYearId);
        if ($teacherId === null) {
            return false;
        }

        return GradeEntryCoverage::anyCovers($seat, $this->scopes($teacherId, $seat));
    }

    private function placement(int $examEnrollmentId, int $schoolId): ?GradeSeatPlacement
    {
        $row = DB::table(SchemaHelper::qualified('exams', 'exam_enrollments').' as ee')
            ->join(SchemaHelper::qualified('exams', 'exam_sessions').' as es', 'es.id', '=', 'ee.exam_session_id')
            ->join(SchemaHelper::qualified('exams', 'exams').' as ex', 'ex.id', '=', 'es.exam_id')
            ->join(SchemaHelper::qualified('enrollment', 'enrollments').' as en', 'en.id', '=', 'ee.enrollment_id')
            ->where('ee.id', $examEnrollmentId)
            ->where('ee.school_id', $schoolId)
            ->first([
                'ee.id', 'ee.school_id', 'ex.academic_year_id', 'es.subject_id',
                'en.branch_id', 'en.department_id', 'en.class_id', 'en.section_id',
            ]);

        if ($row === null) {
            return null;
        }

        return new GradeSeatPlacement(
            examEnrollmentId: (int) $row->id,
            schoolId: (int) $row->school_id,
            academicYearId: (int) $row->academic_year_id,
            subjectId: (int) $row->subject_id,
            branchId: $row->branch_id !== null ? (int) $row->branch_id : null,
            departmentId: $row->department_id !== null ? (int) $row->department_id : null,
            classId: (int) $row->class_id,
            sectionId: (int) $row->section_id,
        );
    }

    /**
     * @return list<TeachingScope>
     */
    private function scopes(int $teacherId, GradeSeatPlacement $seat): array
    {
        $today = now()->toDateString();
        $int = static fn (mixed $v): ?int => $v !== null ? (int) $v : null;

        return DB::table(SchemaHelper::qualified('teachers', 'teaching_assignments'))
            ->where('teacher_id', $teacherId)
            ->where('school_id', $seat->schoolId)
            ->where('academic_year_id', $seat->academicYearId)
            ->where('subject_id', $seat->subjectId)
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
