<?php

namespace Tests\Support\Exams;

use App\Database\SchemaHelper;
use App\Database\StudentGradesPartitionManager;
use App\Domain\Exams\ValueObjects\ExamSessionStatus;
use Illuminate\Support\Facades\DB;

/**
 * SchemaHelper-aware exam/grade graph for SQLite + PostgreSQL application tests.
 */
trait SeedsApplicationExamGradeGraph
{
    /**
     * @return array{
     *   school_id:int,year_id:int,term_id:int,subject_id:int,
     *   enrollment_id:int,student_id:int,exam_id:int,
     *   session_id:int,exam_enrollment_id:int,max_score:string
     * }
     */
    protected function seedExamGradeGraph(int $schoolId, ?int $yearId = null, string $suffix = 'A'): array
    {
        $yearId ??= $this->createAcademicYear('AY-G-'.$suffix);
        StudentGradesPartitionManager::ensurePartitionForAcademicYear($yearId);

        $termId = (int) DB::table(SchemaHelper::qualified('academic', 'terms'))->insertGetId([
            'academic_year_id' => $yearId,
            'code' => 'T-'.$suffix.uniqid(),
            'name' => 'Term '.$suffix,
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-31',
            'term_order' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $typeId = (int) DB::table(SchemaHelper::qualified('exams', 'exam_types'))->insertGetId([
            'code' => 'MID'.substr(uniqid(), -4),
            'name' => 'Midterm '.$suffix,
            'weight_percentage' => 40,
        ]);

        $subjectId = (int) DB::table(SchemaHelper::qualified('curriculum', 'subjects'))->insertGetId([
            'code' => 'SUB'.substr(uniqid(), -4),
            'name' => 'Subject '.$suffix,
            'subject_type' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $enrollment = $this->createActiveEnrollmentForSchool($schoolId, $yearId);

        $examId = (int) DB::table(SchemaHelper::qualified('exams', 'exams'))->insertGetId([
            'academic_year_id' => $yearId,
            'school_id' => $schoolId,
            'term_id' => $termId,
            'exam_type_id' => $typeId,
            'name' => 'Exam '.$suffix,
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-15',
            'status' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sessionId = (int) DB::table(SchemaHelper::qualified('exams', 'exam_sessions'))->insertGetId([
            'exam_id' => $examId,
            'school_id' => $schoolId,
            'subject_id' => $subjectId,
            'session_date' => '2026-11-05',
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'max_grade' => 100,
            'pass_grade' => 50,
            'status' => 1,
            'created_at' => now(),
        ]);

        $examEnrollmentId = (int) DB::table(SchemaHelper::qualified('exams', 'exam_enrollments'))->insertGetId([
            'exam_session_id' => $sessionId,
            'school_id' => $schoolId,
            'enrollment_id' => $enrollment->id,
            'seat_number' => 'A1',
            'status' => 1,
            'created_at' => now(),
        ]);

        return [
            'school_id' => $schoolId,
            'year_id' => $yearId,
            'term_id' => $termId,
            'subject_id' => $subjectId,
            'enrollment_id' => (int) $enrollment->id,
            'student_id' => (int) $enrollment->student_id,
            'exam_id' => $examId,
            'session_id' => $sessionId,
            'exam_enrollment_id' => $examEnrollmentId,
            'max_score' => '100',
        ];
    }

    /**
     * HD-7.2-009 — EnterStudentGrade requires InProgress or Completed session.
     */
    protected function markSessionInProgressForGradeEntry(int $sessionId): void
    {
        DB::table(SchemaHelper::qualified('exams', 'exam_sessions'))
            ->where('id', $sessionId)
            ->update(['status' => ExamSessionStatus::InProgress->value]);
    }
}
