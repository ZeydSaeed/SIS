<?php

namespace Tests\Feature\Security\PostgreSql;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Database\PostgreSqlRlsActor;

/**
 * R1.7 Attendance FORCE RLS — Strategy A (sessions.school_id).
 */
class AttendanceRlsPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    #[Test]
    public function live_catalog_has_force_rls_on_attendance_tables(): void
    {
        $rows = collect(DB::select("
            SELECT c.relname, c.relrowsecurity AS rls, c.relforcerowsecurity AS force_rls
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'attendance'
              AND c.relname IN ('sessions', 'records', 'daily_section_summary')
            ORDER BY c.relname
        "))->keyBy('relname');

        foreach (['daily_section_summary', 'records', 'sessions'] as $table) {
            $this->assertTrue((bool) $rows[$table]->rls, "{$table} RLS enabled");
            $this->assertTrue((bool) $rows[$table]->force_rls, "{$table} FORCE RLS");
        }

        $this->assertNotNull(DB::selectOne("
            SELECT 1 AS ok
            FROM information_schema.columns
            WHERE table_schema = 'attendance'
              AND table_name = 'sessions'
              AND column_name = 'school_id'
        "));
    }

    #[Test]
    public function missing_school_context_is_fail_closed(): void
    {
        $g = $this->seedTwoSchools();
        $this->asSchool($g['school_a']);
        $sessionA = $this->insertSession($g, 'a');
        $this->insertRecord($g, $sessionA, 'a');
        $this->insertSummary($g, 'a');

        PostgreSqlRlsActor::become();
        DB::statement("SELECT set_config('app.current_school_id', '', true)");

        $this->assertSame(0, (int) DB::selectOne('SELECT COUNT(*) AS c FROM attendance.sessions')->c);
        $this->assertSame(0, (int) DB::selectOne('SELECT COUNT(*) AS c FROM attendance.records')->c);
        $this->assertSame(0, (int) DB::selectOne('SELECT COUNT(*) AS c FROM attendance.daily_section_summary')->c);

        PostgreSqlRlsActor::reset();
    }

    #[Test]
    public function school_context_isolates_select_and_blocks_cross_school_writes(): void
    {
        $g = $this->seedTwoSchools();

        $this->asSchool($g['school_a']);
        $sessionA = $this->insertSession($g, 'a');
        $this->insertRecord($g, $sessionA, 'a');
        $this->insertSummary($g, 'a');

        $this->asSchool($g['school_b']);
        $sessionB = $this->insertSession($g, 'b');
        $this->insertRecord($g, $sessionB, 'b');
        $this->insertSummary($g, 'b');

        PostgreSqlRlsActor::become();

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $g['school_a']]);
        $sessionIds = collect(DB::select('SELECT id FROM attendance.sessions'))->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assertSame([$sessionA], $sessionIds);
        $this->assertSame(1, (int) DB::selectOne('SELECT COUNT(*) AS c FROM attendance.records')->c);
        $this->assertSame(1, (int) DB::selectOne('SELECT COUNT(*) AS c FROM attendance.daily_section_summary')->c);

        $crossInsertBlocked = false;
        DB::statement('SAVEPOINT rls_cross_insert');
        try {
            DB::table('attendance.sessions')->insert([
                'school_id' => $g['school_b'],
                'section_id' => $g['section_b'],
                'subject_id' => $g['subject_id'],
                'academic_year_id' => $g['year_id'],
                'session_date' => '2026-10-20',
                'period_id' => null,
                'teacher_id' => $g['teacher_id'],
                'status' => 1,
                'created_at' => now(),
            ]);
            DB::statement('ROLLBACK TO SAVEPOINT rls_cross_insert');
        } catch (\Throwable) {
            DB::statement('ROLLBACK TO SAVEPOINT rls_cross_insert');
            $crossInsertBlocked = true;
        }
        $this->assertTrue($crossInsertBlocked, 'Cross-school session INSERT must fail under RLS');

        $crossUpdate = DB::update(
            'UPDATE attendance.sessions SET status = 2 WHERE id = ?',
            [$sessionB],
        );
        $this->assertSame(0, $crossUpdate);

        DB::statement('SAVEPOINT rls_cross_delete');
        try {
            $deleted = DB::table('attendance.records')->where('session_id', $sessionA)->delete();
            DB::statement('ROLLBACK TO SAVEPOINT rls_cross_delete');
            $this->assertSame(0, $deleted, 'DELETE must not remove attendance records under RLS');
        } catch (\Throwable) {
            DB::statement('ROLLBACK TO SAVEPOINT rls_cross_delete');
        }

        $remaining = (int) DB::selectOne(
            'SELECT COUNT(*) AS c FROM attendance.records WHERE session_id = ?',
            [$sessionA],
        )->c;
        $this->assertSame(1, $remaining);

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $g['school_b']]);
        $this->assertSame([$sessionB], collect(DB::select('SELECT id FROM attendance.sessions'))->pluck('id')->map(fn ($id) => (int) $id)->all());

        PostgreSqlRlsActor::reset();
    }

    #[Test]
    public function same_school_insert_update_succeeds_and_session_school_is_deterministic(): void
    {
        $g = $this->seedTwoSchools();
        PostgreSqlRlsActor::become();
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $g['school_a']]);

        $sessionId = (int) DB::table('attendance.sessions')->insertGetId([
            'school_id' => $g['school_a'],
            'section_id' => $g['section_a'],
            'subject_id' => $g['subject_id'],
            'academic_year_id' => $g['year_id'],
            'session_date' => '2026-10-21',
            'period_id' => null,
            'teacher_id' => $g['teacher_id'],
            'status' => 1,
            'created_at' => now(),
        ]);

        $row = DB::selectOne('SELECT school_id, status FROM attendance.sessions WHERE id = ?', [$sessionId]);
        $this->assertSame($g['school_a'], (int) $row->school_id);

        $updated = DB::update('UPDATE attendance.sessions SET status = 2 WHERE id = ?', [$sessionId]);
        $this->assertSame(1, $updated);

        PostgreSqlRlsActor::reset();
    }

    /**
     * @return array<string, int>
     */
    private function seedTwoSchools(): array
    {
        $suffix = substr(str_replace('.', '', uniqid('', true)), -4);

        $ministryId = (int) DB::table('organization.ministries')->insertGetId([
            'code' => 'MR'.$suffix,
            'name' => 'Ministry R'.$suffix,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $dirId = (int) DB::table('organization.directorates')->insertGetId([
            'ministry_id' => $ministryId,
            'code' => 'DR'.$suffix,
            'name' => 'Dir R'.$suffix,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $schoolA = (int) DB::table('organization.schools')->insertGetId([
            'directorate_id' => $dirId,
            'code' => 'RA'.$suffix,
            'name' => 'School RA',
            'school_type' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $schoolB = (int) DB::table('organization.schools')->insertGetId([
            'directorate_id' => $dirId,
            'code' => 'RB'.$suffix,
            'name' => 'School RB',
            'school_type' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $yearId = (int) DB::table('academic.academic_years')->insertGetId([
            'code' => 'YR'.$suffix,
            'name' => 'Year R',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'is_current' => true,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $gradeId = (int) DB::table('academic.grade_levels')->insertGetId([
            'code' => 'GR'.$suffix,
            'name' => 'Grade R',
            'level_order' => 10,
            'education_stage' => 1,
            'status' => 1,
        ]);
        $subjectId = (int) DB::table('curriculum.subjects')->insertGetId([
            'code' => 'SR'.$suffix,
            'name' => 'Subject R',
            'subject_type' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $teacherId = (int) DB::table('teachers.teachers')->insertGetId([
            'employee_code' => 'TR'.$suffix,
            'first_name' => 'T',
            'last_name' => 'R',
            'full_name' => 'T R',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $classA = (int) DB::table('enrollment.classes')->insertGetId([
            'school_id' => $schoolA,
            'academic_year_id' => $yearId,
            'grade_level_id' => $gradeId,
            'code' => 'CA'.$suffix,
            'name' => 'Class A',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $classB = (int) DB::table('enrollment.classes')->insertGetId([
            'school_id' => $schoolB,
            'academic_year_id' => $yearId,
            'grade_level_id' => $gradeId,
            'code' => 'CB'.$suffix,
            'name' => 'Class B',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $sectionA = (int) DB::table('enrollment.sections')->insertGetId([
            'class_id' => $classA,
            'code' => 'SA',
            'name' => 'Sec A',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $sectionB = (int) DB::table('enrollment.sections')->insertGetId([
            'class_id' => $classB,
            'code' => 'SB',
            'name' => 'Sec B',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $studentA = (int) DB::table('students.students')->insertGetId([
            'school_id' => $schoolA,
            'student_code' => 'SA'.$suffix,
            'first_name' => 'A',
            'last_name' => 'A',
            'full_name' => 'A A',
            'gender' => 1,
            'birth_date' => '2010-01-01',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $studentB = (int) DB::table('students.students')->insertGetId([
            'school_id' => $schoolB,
            'student_code' => 'SB'.$suffix,
            'first_name' => 'B',
            'last_name' => 'B',
            'full_name' => 'B B',
            'gender' => 1,
            'birth_date' => '2010-01-01',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $enrollmentA = (int) DB::table('enrollment.enrollments')->insertGetId([
            'student_id' => $studentA,
            'academic_year_id' => $yearId,
            'school_id' => $schoolA,
            'class_id' => $classA,
            'section_id' => $sectionA,
            'enrollment_number' => 'EA'.$suffix,
            'status' => 1,
            'effective_from' => '2026-09-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $enrollmentB = (int) DB::table('enrollment.enrollments')->insertGetId([
            'student_id' => $studentB,
            'academic_year_id' => $yearId,
            'school_id' => $schoolB,
            'class_id' => $classB,
            'section_id' => $sectionB,
            'enrollment_number' => 'EB'.$suffix,
            'status' => 1,
            'effective_from' => '2026-09-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'school_a' => $schoolA,
            'school_b' => $schoolB,
            'year_id' => $yearId,
            'subject_id' => $subjectId,
            'teacher_id' => $teacherId,
            'section_a' => $sectionA,
            'section_b' => $sectionB,
            'student_a' => $studentA,
            'student_b' => $studentB,
            'enrollment_a' => $enrollmentA,
            'enrollment_b' => $enrollmentB,
        ];
    }

    private function asSchool(int $schoolId): void
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, false)", [(string) $schoolId]);
    }

    /**
     * @param  array<string, int>  $g
     */
    private function insertSession(array $g, string $which): int
    {
        $schoolId = $which === 'a' ? $g['school_a'] : $g['school_b'];
        $sectionId = $which === 'a' ? $g['section_a'] : $g['section_b'];

        return (int) DB::table('attendance.sessions')->insertGetId([
            'school_id' => $schoolId,
            'section_id' => $sectionId,
            'subject_id' => $g['subject_id'],
            'academic_year_id' => $g['year_id'],
            'session_date' => '2026-10-15',
            'period_id' => null,
            'teacher_id' => $g['teacher_id'],
            'status' => 1,
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<string, int>  $g
     */
    private function insertRecord(array $g, int $sessionId, string $which): void
    {
        $schoolId = $which === 'a' ? $g['school_a'] : $g['school_b'];
        $studentId = $which === 'a' ? $g['student_a'] : $g['student_b'];
        $enrollmentId = $which === 'a' ? $g['enrollment_a'] : $g['enrollment_b'];

        DB::table('attendance.records')->insert([
            'session_id' => $sessionId,
            'student_id' => $studentId,
            'enrollment_id' => $enrollmentId,
            'academic_year_id' => $g['year_id'],
            'school_id' => $schoolId,
            'attendance_date' => '2026-10-15',
            'status' => 1,
            'notes' => null,
            'recorded_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, int>  $g
     */
    private function insertSummary(array $g, string $which): void
    {
        $schoolId = $which === 'a' ? $g['school_a'] : $g['school_b'];
        $sectionId = $which === 'a' ? $g['section_a'] : $g['section_b'];

        DB::table('attendance.daily_section_summary')->insert([
            'section_id' => $sectionId,
            'school_id' => $schoolId,
            'academic_year_id' => $g['year_id'],
            'attendance_date' => '2026-10-15',
            'total_students' => 1,
            'present_count' => 1,
            'absent_count' => 0,
            'late_count' => 0,
            'updated_at' => now(),
        ]);
    }
}
