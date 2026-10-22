<?php

namespace App\Infrastructure\Persistence\Exams;

use App\Application\Exams\Contracts\GradeEntrySheetReadRepositoryInterface;
use App\Database\SchemaHelper;
use App\Domain\Exams\ValueObjects\ExamEnrollmentStatus;
use App\Domain\Exams\ValueObjects\ExamSessionStatus;
use App\Domain\Exams\ValueObjects\ExamStatus;
use Illuminate\Support\Facades\DB;

final class EloquentGradeEntrySheetReadRepository implements GradeEntrySheetReadRepositoryInterface
{
    public function sessions(int $schoolId, int $academicYearId, ?array $onlySessionIds): array
    {
        if ($onlySessionIds === []) {
            return [];
        }

        return DB::table(SchemaHelper::qualified('exams', 'exam_sessions').' as es')
            ->join(SchemaHelper::qualified('exams', 'exams').' as ex', 'ex.id', '=', 'es.exam_id')
            ->join(SchemaHelper::qualified('curriculum', 'subjects').' as sub', 'sub.id', '=', 'es.subject_id')
            ->where('es.school_id', $schoolId)
            ->where('ex.academic_year_id', $academicYearId)
            ->where('es.status', '<>', ExamSessionStatus::Cancelled->value)
            ->where('ex.status', '<>', ExamStatus::Cancelled->value)
            ->when($onlySessionIds !== null, static fn ($q) => $q->whereIn('es.id', $onlySessionIds))
            ->orderBy('es.session_date')
            ->orderBy('es.start_time')
            ->orderBy('es.id')
            ->get(['es.id', 'ex.name as exam_name', 'sub.name as subject_name', 'es.session_date', 'es.status', 'es.max_grade'])
            ->map(static fn (object $r): array => [
                'id' => (int) $r->id,
                'exam_name' => (string) $r->exam_name,
                'subject_name' => (string) $r->subject_name,
                'session_date' => (string) $r->session_date,
                'status' => (int) $r->status,
                'max_grade' => (int) $r->max_grade,
            ])
            ->all();
    }

    public function session(int $schoolId, int $examSessionId): ?array
    {
        $r = DB::table(SchemaHelper::qualified('exams', 'exam_sessions').' as es')
            ->join(SchemaHelper::qualified('exams', 'exams').' as ex', 'ex.id', '=', 'es.exam_id')
            ->join(SchemaHelper::qualified('academic', 'academic_years').' as ay', 'ay.id', '=', 'ex.academic_year_id')
            ->join(SchemaHelper::qualified('academic', 'terms').' as tm', 'tm.id', '=', 'ex.term_id')
            ->join(SchemaHelper::qualified('curriculum', 'subjects').' as sub', 'sub.id', '=', 'es.subject_id')
            ->where('es.school_id', $schoolId)
            ->where('es.id', $examSessionId)
            ->first([
                'es.id', 'ex.academic_year_id', 'ay.name as academic_year_name', 'ex.name as exam_name', 'ex.status as exam_status',
                'tm.name as term_name', 'sub.name as subject_name', 'es.session_date', 'es.start_time', 'es.end_time',
                'es.status', 'es.max_grade', 'es.pass_grade',
            ]);

        if ($r === null) {
            return null;
        }

        return [
            'id' => (int) $r->id,
            'academic_year_id' => (int) $r->academic_year_id,
            'academic_year_name' => (string) $r->academic_year_name,
            'exam_name' => (string) $r->exam_name,
            'exam_status' => (int) $r->exam_status,
            'term_name' => (string) $r->term_name,
            'subject_name' => (string) $r->subject_name,
            'session_date' => (string) $r->session_date,
            'start_time' => substr((string) $r->start_time, 0, 5),
            'end_time' => substr((string) $r->end_time, 0, 5),
            'status' => (int) $r->status,
            'max_grade' => (int) $r->max_grade,
            'pass_grade' => (int) $r->pass_grade,
        ];
    }

    public function seats(int $schoolId, int $examSessionId, ?array $onlySeatIds): array
    {
        if ($onlySeatIds === []) {
            return [];
        }

        $active = array_map(
            static fn (ExamEnrollmentStatus $s): int => $s->value,
            array_filter(ExamEnrollmentStatus::cases(), static fn (ExamEnrollmentStatus $s): bool => $s->isActiveSeat()),
        );

        return DB::table(SchemaHelper::qualified('exams', 'exam_enrollments').' as ee')
            ->join(SchemaHelper::qualified('exams', 'exam_sessions').' as es', 'es.id', '=', 'ee.exam_session_id')
            ->join(SchemaHelper::qualified('exams', 'exams').' as ex', 'ex.id', '=', 'es.exam_id')
            ->join(SchemaHelper::qualified('enrollment', 'enrollments').' as en', 'en.id', '=', 'ee.enrollment_id')
            ->join(SchemaHelper::qualified('students', 'students').' as st', 'st.id', '=', 'en.student_id')
            ->join(SchemaHelper::qualified('enrollment', 'classes').' as cl', 'cl.id', '=', 'en.class_id')
            ->join(SchemaHelper::qualified('enrollment', 'sections').' as sc', 'sc.id', '=', 'en.section_id')
            ->leftJoin(SchemaHelper::qualified('exams', 'student_grades').' as g', function ($join): void {
                $join->on('g.exam_enrollment_id', '=', 'ee.id')
                    ->on('g.academic_year_id', '=', 'ex.academic_year_id')
                    ->where('g.is_current', true);
            })
            ->where('ee.school_id', $schoolId)
            ->where('ee.exam_session_id', $examSessionId)
            ->whereIn('ee.status', array_values($active))
            ->when($onlySeatIds !== null, static fn ($q) => $q->whereIn('ee.id', $onlySeatIds))
            ->orderBy('cl.name')
            ->orderBy('sc.name')
            ->orderBy('st.full_name')
            ->orderBy('ee.id')
            ->get([
                'ee.id as exam_enrollment_id', 'ee.seat_number', 'st.student_code', 'st.full_name',
                'cl.name as class_name', 'sc.name as section_name',
                'g.id as grade_id', 'g.score', 'g.is_absent', 'g.status as grade_status',
            ])
            ->map(static fn (object $r): array => [
                'exam_enrollment_id' => (int) $r->exam_enrollment_id,
                'seat_number' => $r->seat_number !== null ? (string) $r->seat_number : null,
                'student_code' => (string) $r->student_code,
                'student_name' => (string) $r->full_name,
                'class_name' => (string) $r->class_name,
                'section_name' => (string) $r->section_name,
                'grade_id' => $r->grade_id !== null ? (int) $r->grade_id : null,
                'score' => $r->score !== null ? (string) $r->score : null,
                'is_absent' => (bool) $r->is_absent,
                'grade_status' => $r->grade_status !== null ? (int) $r->grade_status : null,
            ])
            ->all();
    }
}
