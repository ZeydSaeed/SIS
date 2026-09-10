<?php

namespace Tests\Feature\Database\PostgreSql;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

class ExamFoundationCheckConstraintPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    #[Test]
    public function exam_rejects_end_before_start(): void
    {
        $ctx = $this->seedMinimalExamContext();
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $ctx['school_id']]);

        $this->expectException(QueryException::class);
        DB::table('exams.exams')->insert([
            'academic_year_id' => $ctx['year_id'],
            'school_id' => $ctx['school_id'],
            'term_id' => $ctx['term_id'],
            'exam_type_id' => $ctx['type_id'],
            'name' => 'Bad Dates',
            'start_date' => '2026-11-15',
            'end_date' => '2026-11-01',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    #[Test]
    public function exam_rejects_invalid_status(): void
    {
        $ctx = $this->seedMinimalExamContext();
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $ctx['school_id']]);

        $this->expectException(QueryException::class);
        DB::table('exams.exams')->insert([
            'academic_year_id' => $ctx['year_id'],
            'school_id' => $ctx['school_id'],
            'term_id' => $ctx['term_id'],
            'exam_type_id' => $ctx['type_id'],
            'name' => 'Bad Status',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-15',
            'status' => 99,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    #[Test]
    public function exam_type_rejects_invalid_weight(): void
    {
        $this->expectException(QueryException::class);
        DB::table('exams.exam_types')->insert([
            'code' => 'BAD',
            'name' => 'Bad Weight',
            'weight_percentage' => 150,
        ]);
    }

    #[Test]
    public function session_rejects_end_time_before_start_time(): void
    {
        $ctx = $this->seedMinimalExamContext();
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $ctx['school_id']]);

        $examId = (int) DB::table('exams.exams')->insertGetId([
            'academic_year_id' => $ctx['year_id'],
            'school_id' => $ctx['school_id'],
            'term_id' => $ctx['term_id'],
            'exam_type_id' => $ctx['type_id'],
            'name' => 'OK Exam',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-15',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        DB::table('exams.exam_sessions')->insert([
            'exam_id' => $examId,
            'school_id' => $ctx['school_id'],
            'subject_id' => $ctx['subject_id'],
            'session_date' => '2026-11-05',
            'start_time' => '11:00:00',
            'end_time' => '09:00:00',
            'max_grade' => 100,
            'pass_grade' => 50,
            'status' => 1,
            'created_at' => now(),
        ]);
    }

    #[Test]
    public function duplicate_exam_enrollment_is_rejected(): void
    {
        $ctx = $this->seedFullEnrollmentContext();
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $ctx['school_id']]);
        $sessionId = $this->insertSession($ctx);

        DB::table('exams.exam_enrollments')->insert([
            'exam_session_id' => $sessionId,
            'school_id' => $ctx['school_id'],
            'enrollment_id' => $ctx['enrollment_id'],
            'seat_number' => 'A1',
            'status' => 1,
            'created_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        DB::table('exams.exam_enrollments')->insert([
            'exam_session_id' => $sessionId,
            'school_id' => $ctx['school_id'],
            'enrollment_id' => $ctx['enrollment_id'],
            'seat_number' => 'A2',
            'status' => 1,
            'created_at' => now(),
        ]);
    }

    #[Test]
    public function cross_school_exam_enrollment_is_rejected(): void
    {
        $ctx = $this->seedFullEnrollmentContext();
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $ctx['school_id']]);
        $sessionId = $this->insertSession($ctx);

        $this->expectException(QueryException::class);
        DB::table('exams.exam_enrollments')->insert([
            'exam_session_id' => $sessionId,
            'school_id' => $ctx['school_id'],
            'enrollment_id' => $ctx['other_school_enrollment_id'],
            'seat_number' => 'B1',
            'status' => 1,
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array{school_id:int,year_id:int,term_id:int,type_id:int,subject_id:int}  $ctx
     */
    private function insertSession(array $ctx): int
    {
        $examId = (int) DB::table('exams.exams')->insertGetId([
            'academic_year_id' => $ctx['year_id'],
            'school_id' => $ctx['school_id'],
            'term_id' => $ctx['term_id'],
            'exam_type_id' => $ctx['type_id'],
            'name' => 'Seat Exam',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-15',
            'status' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('exams.exam_sessions')->insertGetId([
            'exam_id' => $examId,
            'school_id' => $ctx['school_id'],
            'subject_id' => $ctx['subject_id'],
            'session_date' => '2026-11-05',
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'max_grade' => 100,
            'pass_grade' => 50,
            'status' => 1,
            'created_at' => now(),
        ]);
    }

    #[Test]
    public function valid_exam_tree_insert_succeeds(): void
    {
        $ctx = $this->seedFullEnrollmentContext();
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $ctx['school_id']]);

        $examId = (int) DB::table('exams.exams')->insertGetId([
            'academic_year_id' => $ctx['year_id'],
            'school_id' => $ctx['school_id'],
            'term_id' => $ctx['term_id'],
            'exam_type_id' => $ctx['type_id'],
            'name' => 'Valid Exam',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-15',
            'status' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $sessionId = (int) DB::table('exams.exam_sessions')->insertGetId([
            'exam_id' => $examId,
            'school_id' => $ctx['school_id'],
            'subject_id' => $ctx['subject_id'],
            'session_date' => '2026-11-05',
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'max_grade' => 100,
            'pass_grade' => 50,
            'status' => 1,
            'created_at' => now(),
        ]);
        $seatId = (int) DB::table('exams.exam_enrollments')->insertGetId([
            'exam_session_id' => $sessionId,
            'school_id' => $ctx['school_id'],
            'enrollment_id' => $ctx['enrollment_id'],
            'seat_number' => 'C1',
            'status' => 1,
            'created_at' => now(),
        ]);

        $this->assertGreaterThan(0, $examId);
        $this->assertGreaterThan(0, $sessionId);
        $this->assertGreaterThan(0, $seatId);
    }

    /**
     * @return array{school_id:int,year_id:int,term_id:int,type_id:int,subject_id:int}
     */
    private function seedMinimalExamContext(): array
    {
        $ministryId = (int) DB::table('organization.ministries')->insertGetId([
            'code' => 'MOE-CK',
            'name' => 'Ministry CK',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $dirId = (int) DB::table('organization.directorates')->insertGetId([
            'ministry_id' => $ministryId,
            'code' => 'DIR-CK',
            'name' => 'Dir CK',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $schoolId = (int) DB::table('organization.schools')->insertGetId([
            'directorate_id' => $dirId,
            'code' => 'SCH-CK',
            'name' => 'School CK',
            'school_type' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $yearId = (int) DB::table('academic.academic_years')->insertGetId([
            'code' => 'AY-CK',
            'name' => 'Year CK',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'is_current' => true,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $termId = (int) DB::table('academic.terms')->insertGetId([
            'academic_year_id' => $yearId,
            'code' => 'T1',
            'name' => 'Term 1',
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-31',
            'term_order' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $typeId = (int) DB::table('exams.exam_types')->insertGetId([
            'code' => 'FIN',
            'name' => 'Final',
            'weight_percentage' => 60,
        ]);
        $subjectId = (int) DB::table('curriculum.subjects')->insertGetId([
            'code' => 'SCI',
            'name' => 'Science',
            'subject_type' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'school_id' => $schoolId,
            'year_id' => $yearId,
            'term_id' => $termId,
            'type_id' => $typeId,
            'subject_id' => $subjectId,
        ];
    }

    /**
     * @return array{school_id:int,year_id:int,term_id:int,type_id:int,subject_id:int,enrollment_id:int,other_school_enrollment_id:int}
     */
    private function seedFullEnrollmentContext(): array
    {
        $base = $this->seedMinimalExamContext();

        $gradeId = (int) DB::table('academic.grade_levels')->insertGetId([
            'code' => 'G10C',
            'name' => 'Grade 10',
            'level_order' => 10,
            'education_stage' => 1,
            'status' => 1,
        ]);

        $classId = (int) DB::table('enrollment.classes')->insertGetId([
            'school_id' => $base['school_id'],
            'academic_year_id' => $base['year_id'],
            'grade_level_id' => $gradeId,
            'code' => 'C1',
            'name' => 'Class 1',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $sectionId = (int) DB::table('enrollment.sections')->insertGetId([
            'class_id' => $classId,
            'code' => 'S1',
            'name' => 'Section 1',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $studentId = (int) DB::table('students.students')->insertGetId([
            'school_id' => $base['school_id'],
            'student_code' => 'STU-CK-1',
            'first_name' => 'Sara',
            'last_name' => 'Test',
            'full_name' => 'Sara Test',
            'gender' => 2,
            'birth_date' => '2011-02-02',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $enrollmentId = (int) DB::table('enrollment.enrollments')->insertGetId([
            'student_id' => $studentId,
            'academic_year_id' => $base['year_id'],
            'school_id' => $base['school_id'],
            'class_id' => $classId,
            'section_id' => $sectionId,
            'enrollment_number' => 'ENR-CK-1',
            'status' => 1,
            'effective_from' => '2026-09-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $otherSchoolId = (int) DB::table('organization.schools')->insertGetId([
            'directorate_id' => (int) DB::table('organization.schools')->where('id', $base['school_id'])->value('directorate_id'),
            'code' => 'SCH-CK-B',
            'name' => 'School CK B',
            'school_type' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $otherClassId = (int) DB::table('enrollment.classes')->insertGetId([
            'school_id' => $otherSchoolId,
            'academic_year_id' => $base['year_id'],
            'grade_level_id' => $gradeId,
            'code' => 'C2',
            'name' => 'Class 2',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $otherSectionId = (int) DB::table('enrollment.sections')->insertGetId([
            'class_id' => $otherClassId,
            'code' => 'S1',
            'name' => 'Section 1',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $otherStudentId = (int) DB::table('students.students')->insertGetId([
            'school_id' => $otherSchoolId,
            'student_code' => 'STU-CK-2',
            'first_name' => 'Omar',
            'last_name' => 'Other',
            'full_name' => 'Omar Other',
            'gender' => 1,
            'birth_date' => '2010-03-03',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $otherEnrollmentId = (int) DB::table('enrollment.enrollments')->insertGetId([
            'student_id' => $otherStudentId,
            'academic_year_id' => $base['year_id'],
            'school_id' => $otherSchoolId,
            'class_id' => $otherClassId,
            'section_id' => $otherSectionId,
            'enrollment_number' => 'ENR-CK-2',
            'status' => 1,
            'effective_from' => '2026-09-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return array_merge($base, [
            'enrollment_id' => $enrollmentId,
            'other_school_enrollment_id' => $otherEnrollmentId,
        ]);
    }
}
