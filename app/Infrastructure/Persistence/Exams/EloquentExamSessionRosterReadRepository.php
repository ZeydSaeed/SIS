<?php

namespace App\Infrastructure\Persistence\Exams;

use App\Application\Exams\Contracts\ExamSessionRosterReadRepositoryInterface;
use App\Database\SchemaHelper;
use Illuminate\Support\Facades\DB;

final class EloquentExamSessionRosterReadRepository implements ExamSessionRosterReadRepositoryInterface
{
    public function session(int $schoolId, int $examSessionId): ?array
    {
        $r = DB::table(SchemaHelper::qualified('exams', 'exam_sessions').' as es')
            ->join(SchemaHelper::qualified('exams', 'exams').' as ex', 'ex.id', '=', 'es.exam_id')
            ->join(SchemaHelper::qualified('curriculum', 'subjects').' as sub', 'sub.id', '=', 'es.subject_id')
            ->where('es.school_id', $schoolId)
            ->where('es.id', $examSessionId)
            ->first([
                'es.id', 'es.exam_id', 'ex.name as exam_name', 'ex.academic_year_id', 'sub.name as subject_name',
                'es.session_date', 'es.start_time', 'es.end_time', 'es.status',
            ]);

        if ($r === null) {
            return null;
        }

        return [
            'id' => (int) $r->id,
            'exam_id' => (int) $r->exam_id,
            'exam_name' => (string) $r->exam_name,
            'academic_year_id' => (int) $r->academic_year_id,
            'subject_name' => (string) $r->subject_name,
            'session_date' => (string) $r->session_date,
            'start_time' => substr((string) $r->start_time, 0, 5),
            'end_time' => substr((string) $r->end_time, 0, 5),
            'status' => (int) $r->status,
        ];
    }

    public function seats(int $schoolId, int $examSessionId): array
    {
        return DB::table(SchemaHelper::qualified('exams', 'exam_enrollments').' as ee')
            ->join(SchemaHelper::qualified('exams', 'exam_sessions').' as es', 'es.id', '=', 'ee.exam_session_id')
            ->join(SchemaHelper::qualified('exams', 'exams').' as ex', 'ex.id', '=', 'es.exam_id')
            ->join(SchemaHelper::qualified('enrollment', 'enrollments').' as en', 'en.id', '=', 'ee.enrollment_id')
            ->join(SchemaHelper::qualified('students', 'students').' as st', 'st.id', '=', 'en.student_id')
            ->leftJoin(SchemaHelper::qualified('enrollment', 'classes').' as cl', 'cl.id', '=', 'en.class_id')
            ->leftJoin(SchemaHelper::qualified('enrollment', 'sections').' as sc', 'sc.id', '=', 'en.section_id')
            ->where('ee.school_id', $schoolId)
            ->where('ee.exam_session_id', $examSessionId)
            ->orderBy('cl.name')
            ->orderBy('sc.name')
            ->orderBy('st.full_name')
            ->orderBy('ee.id')
            ->selectRaw('ee.id as exam_enrollment_id, ee.seat_number, ee.status, st.student_code, st.full_name, cl.name as class_name, sc.name as section_name')
            ->selectRaw('exists (select 1 from '.SchemaHelper::qualified('exams', 'student_grades').' g where g.exam_enrollment_id = ee.id'
                .' and g.academic_year_id = ex.academic_year_id and g.is_current) as has_current_grade')
            ->get()
            ->map(static fn (object $r): array => [
                'exam_enrollment_id' => (int) $r->exam_enrollment_id,
                'seat_number' => $r->seat_number !== null ? (string) $r->seat_number : null,
                'status' => (int) $r->status,
                'student_code' => (string) $r->student_code,
                'student_name' => (string) $r->full_name,
                'class_name' => $r->class_name !== null ? (string) $r->class_name : null,
                'section_name' => $r->section_name !== null ? (string) $r->section_name : null,
                'has_current_grade' => (bool) $r->has_current_grade,
            ])
            ->all();
    }
}
