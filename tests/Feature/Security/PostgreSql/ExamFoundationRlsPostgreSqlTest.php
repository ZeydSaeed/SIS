<?php

namespace Tests\Feature\Security\PostgreSql;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Database\PostgreSqlRlsActor;

class ExamFoundationRlsPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    #[Test]
    public function missing_school_context_is_fail_closed_for_exams(): void
    {
        $ctx = $this->seedExamGraphForSchool('A');

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $ctx['school_id']]);
        $this->insertExamTree($ctx, 'Hidden Exam');

        PostgreSqlRlsActor::become();
        DB::statement("SELECT set_config('app.current_school_id', '', true)");

        $this->assertSame(0, (int) DB::selectOne('SELECT COUNT(*) AS c FROM exams.exams')->c);
        $this->assertSame(0, (int) DB::selectOne('SELECT COUNT(*) AS c FROM exams.exam_sessions')->c);
        $this->assertSame(0, (int) DB::selectOne('SELECT COUNT(*) AS c FROM exams.exam_enrollments')->c);

        // Global reference remains visible without school context.
        $this->assertGreaterThan(0, (int) DB::selectOne('SELECT COUNT(*) AS c FROM exams.exam_types')->c);

        PostgreSqlRlsActor::reset();
    }

    #[Test]
    public function school_context_isolates_exam_rows_across_schools(): void
    {
        $schoolA = $this->seedExamGraphForSchool('A');
        $schoolB = $this->seedExamGraphForSchool('B', reuseYearAndType: $schoolA);

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolA['school_id']]);
        [$examA, $sessionA, $enrollmentSeatA] = $this->insertExamTree($schoolA, 'Exam A');

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolB['school_id']]);
        [$examB, $sessionB, $enrollmentSeatB] = $this->insertExamTree($schoolB, 'Exam B');

        PostgreSqlRlsActor::become();

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolA['school_id']]);
        $examIds = collect(DB::select('SELECT id FROM exams.exams'))->pluck('id')->map(fn ($id) => (int) $id)->all();
        $sessionIds = collect(DB::select('SELECT id FROM exams.exam_sessions'))->pluck('id')->map(fn ($id) => (int) $id)->all();
        $seatIds = collect(DB::select('SELECT id FROM exams.exam_enrollments'))->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertSame([$examA], $examIds);
        $this->assertSame([$sessionA], $sessionIds);
        $this->assertSame([$enrollmentSeatA], $seatIds);
        $this->assertNotContains($examB, $examIds);
        $this->assertNotContains($sessionB, $sessionIds);
        $this->assertNotContains($enrollmentSeatB, $seatIds);

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolB['school_id']]);
        $examIdsB = collect(DB::select('SELECT id FROM exams.exams'))->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assertSame([$examB], $examIdsB);

        DB::statement("SELECT set_config('app.current_school_id', '999999', true)");
        $this->assertSame(0, (int) DB::selectOne('SELECT COUNT(*) AS c FROM exams.exams')->c);

        DB::statement("SELECT set_config('app.current_school_id', '', true)");
        $this->assertSame(0, (int) DB::selectOne('SELECT COUNT(*) AS c FROM exams.exams')->c);

        PostgreSqlRlsActor::reset();
    }

    /**
     * @param  array{school_id:int,year_id:int,term_id:int,type_id:int,subject_id:int,enrollment_id:int}|null  $reuseYearAndType
     * @return array{school_id:int,year_id:int,term_id:int,type_id:int,subject_id:int,enrollment_id:int}
     */
    private function seedExamGraphForSchool(string $suffix, ?array $reuseYearAndType = null): array
    {
        $ministryId = (int) DB::table('organization.ministries')->insertGetId([
            'code' => 'MOE-EX-'.$suffix,
            'name' => 'Ministry '.$suffix,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $dirId = (int) DB::table('organization.directorates')->insertGetId([
            'ministry_id' => $ministryId,
            'code' => 'DIR-EX-'.$suffix,
            'name' => 'Dir '.$suffix,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $schoolId = (int) DB::table('organization.schools')->insertGetId([
            'directorate_id' => $dirId,
            'code' => 'SCH-EX-'.$suffix,
            'name' => 'School '.$suffix,
            'school_type' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($reuseYearAndType !== null) {
            $yearId = $reuseYearAndType['year_id'];
            $termId = $reuseYearAndType['term_id'];
            $typeId = $reuseYearAndType['type_id'];
            $subjectId = $reuseYearAndType['subject_id'];
            $gradeId = $reuseYearAndType['grade_id'];
        } else {
            $yearId = (int) DB::table('academic.academic_years')->insertGetId([
                'code' => 'AY-EX',
                'name' => 'Exam Year',
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
                'code' => 'MID',
                'name' => 'Midterm',
                'weight_percentage' => 40,
            ]);
            $subjectId = (int) DB::table('curriculum.subjects')->insertGetId([
                'code' => 'MATH',
                'name' => 'Mathematics',
                'subject_type' => 1,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $gradeId = (int) DB::table('academic.grade_levels')->insertGetId([
                'code' => 'G10E',
                'name' => 'Grade 10',
                'level_order' => 10,
                'education_stage' => 1,
                'status' => 1,
            ]);
        }

        $classId = (int) DB::table('enrollment.classes')->insertGetId([
            'school_id' => $schoolId,
            'academic_year_id' => $yearId,
            'grade_level_id' => $gradeId,
            'code' => 'C-'.$suffix,
            'name' => 'Class '.$suffix,
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
            'school_id' => $schoolId,
            'student_code' => 'STU-EX-'.$suffix,
            'first_name' => 'Ali',
            'last_name' => 'Student',
            'full_name' => 'Ali Student',
            'gender' => 1,
            'birth_date' => '2010-01-01',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $enrollmentId = (int) DB::table('enrollment.enrollments')->insertGetId([
            'student_id' => $studentId,
            'academic_year_id' => $yearId,
            'school_id' => $schoolId,
            'class_id' => $classId,
            'section_id' => $sectionId,
            'enrollment_number' => 'ENR-EX-'.$suffix,
            'status' => 1,
            'effective_from' => '2026-09-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'school_id' => $schoolId,
            'year_id' => $yearId,
            'term_id' => $termId,
            'type_id' => $typeId,
            'subject_id' => $subjectId,
            'enrollment_id' => $enrollmentId,
            'grade_id' => $gradeId,
        ];
    }

    /**
     * @param  array{school_id:int,year_id:int,term_id:int,type_id:int,subject_id:int,enrollment_id:int}  $ctx
     * @return array{0:int,1:int,2:int}
     */
    private function insertExamTree(array $ctx, string $name): array
    {
        $examId = (int) DB::table('exams.exams')->insertGetId([
            'academic_year_id' => $ctx['year_id'],
            'school_id' => $ctx['school_id'],
            'term_id' => $ctx['term_id'],
            'exam_type_id' => $ctx['type_id'],
            'name' => $name,
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
            'room_id' => null,
            'max_grade' => 100,
            'pass_grade' => 50,
            'status' => 1,
            'created_at' => now(),
        ]);
        $seatId = (int) DB::table('exams.exam_enrollments')->insertGetId([
            'exam_session_id' => $sessionId,
            'school_id' => $ctx['school_id'],
            'enrollment_id' => $ctx['enrollment_id'],
            'seat_number' => 'A1',
            'status' => 1,
            'created_at' => now(),
        ]);

        return [$examId, $sessionId, $seatId];
    }
}
