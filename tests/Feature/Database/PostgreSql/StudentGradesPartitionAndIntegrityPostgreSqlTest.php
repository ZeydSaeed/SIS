<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\StudentGradesPartitionManager;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Database\SeedsExamGradeGraph;

class StudentGradesPartitionAndIntegrityPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use SeedsExamGradeGraph;

    #[Test]
    public function grades_route_to_correct_partitions_and_missing_partition_fails(): void
    {
        $g = $this->seedTwoSchoolGradeGraph();

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $g['school_a']]);
        $idA = $this->insertGrade([
            'year_id' => $g['year_a'],
            'school_id' => $g['school_a'],
            'exam_enrollment_id' => $g['exam_enrollment_a'],
            'session_id' => $g['session_a'],
            'enrollment_id' => $g['enrollment_a'],
            'student_id' => $g['student_a'],
            'subject_id' => $g['subject_id'],
        ], ['score' => 71]);

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $g['school_b']]);
        $idB = $this->insertGrade([
            'year_id' => $g['year_b'],
            'school_id' => $g['school_b'],
            'exam_enrollment_id' => $g['exam_enrollment_b'],
            'session_id' => $g['session_b'],
            'enrollment_id' => $g['enrollment_b'],
            'student_id' => $g['student_b'],
            'subject_id' => $g['subject_id'],
        ], ['score' => 82]);

        $partA = StudentGradesPartitionManager::partitionTableName($g['year_a']);
        $partB = StudentGradesPartitionManager::partitionTableName($g['year_b']);

        $this->assertSame(1, (int) DB::selectOne("SELECT COUNT(*) AS c FROM exams.{$partA} WHERE id = ?", [$idA])->c);
        $this->assertSame(1, (int) DB::selectOne("SELECT COUNT(*) AS c FROM exams.{$partB} WHERE id = ?", [$idB])->c);
        $this->assertSame(0, (int) DB::selectOne("SELECT COUNT(*) AS c FROM exams.{$partA} WHERE id = ?", [$idB])->c);

        $orphanYear = (int) DB::table('academic.academic_years')->insertGetId([
            'code' => 'AY-ORPH',
            'name' => 'Orphan Year',
            'start_date' => '2027-09-01',
            'end_date' => '2028-06-30',
            'is_current' => false,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        // Intentionally do NOT create a student_grades partition for orphanYear.

        $termO = (int) DB::table('academic.terms')->insertGetId([
            'academic_year_id' => $orphanYear,
            'code' => 'T1O',
            'name' => 'Term O',
            'start_date' => '2027-09-01',
            'end_date' => '2027-12-31',
            'term_order' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $seatO = $this->seedSchoolSeat(
            $g['school_a'],
            $orphanYear,
            $termO,
            $g['type_id'],
            $g['subject_id'],
            $g['grade_level_id'],
            'O',
        );

        $this->expectException(QueryException::class);
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $g['school_a']]);
        DB::table('exams.student_grades')->insert([
            'academic_year_id' => $orphanYear,
            'school_id' => $g['school_a'],
            'exam_enrollment_id' => $seatO['exam_enrollment_id'],
            'exam_session_id' => $seatO['session_id'],
            'enrollment_id' => $seatO['enrollment_id'],
            'student_id' => $seatO['student_id'],
            'subject_id' => $g['subject_id'],
            'score' => 50,
            'max_score' => 100,
            'is_absent' => false,
            'status' => 2,
            'is_current' => true,
            'entered_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    #[Test]
    public function correction_chain_and_duplicate_current_are_enforced(): void
    {
        $g = $this->seedTwoSchoolGradeGraph();
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $g['school_a']]);

        $base = [
            'year_id' => $g['year_a'],
            'school_id' => $g['school_a'],
            'exam_enrollment_id' => $g['exam_enrollment_a'],
            'session_id' => $g['session_a'],
            'enrollment_id' => $g['enrollment_a'],
            'student_id' => $g['student_a'],
            'subject_id' => $g['subject_id'],
        ];

        $idA = $this->insertGrade($base, ['score' => 70, 'is_current' => true, 'status' => 4]);

        DB::table('exams.student_grades')
            ->where('id', $idA)
            ->where('academic_year_id', $g['year_a'])
            ->update(['is_current' => false, 'status' => 5, 'updated_at' => now()]);

        $idB = $this->insertGrade($base, [
            'score' => 78,
            'is_current' => true,
            'status' => 4,
            'correction_of_grade_id' => $idA,
        ]);

        DB::table('exams.student_grades')
            ->where('id', $idB)
            ->where('academic_year_id', $g['year_a'])
            ->update(['is_current' => false, 'status' => 5, 'updated_at' => now()]);

        $idC = $this->insertGrade($base, [
            'score' => 85,
            'is_current' => true,
            'status' => 4,
            'correction_of_grade_id' => $idB,
        ]);

        $rows = collect(DB::select('
            SELECT id, score, is_current, status, correction_of_grade_id
            FROM exams.student_grades
            WHERE exam_enrollment_id = ?
            ORDER BY id
        ', [$g['exam_enrollment_a']]));

        $this->assertCount(3, $rows);
        $this->assertFalse((bool) $rows[0]->is_current);
        $this->assertSame(5, (int) $rows[0]->status);
        $this->assertFalse((bool) $rows[1]->is_current);
        $this->assertTrue((bool) $rows[2]->is_current);
        $this->assertSame($idA, (int) $rows[1]->correction_of_grade_id);
        $this->assertSame($idB, (int) $rows[2]->correction_of_grade_id);
        $this->assertSame($idC, (int) $rows[2]->id);

        $this->expectException(QueryException::class);
        $this->insertGrade($base, ['score' => 90, 'is_current' => true, 'status' => 2]);
    }

    #[Test]
    public function score_checks_and_self_correction_and_hard_delete_are_blocked(): void
    {
        $g = $this->seedTwoSchoolGradeGraph();
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $g['school_a']]);
        $base = [
            'year_id' => $g['year_a'],
            'school_id' => $g['school_a'],
            'exam_enrollment_id' => $g['exam_enrollment_a'],
            'session_id' => $g['session_a'],
            'enrollment_id' => $g['enrollment_a'],
            'student_id' => $g['student_a'],
            'subject_id' => $g['subject_id'],
        ];

        try {
            DB::statement('SAVEPOINT sp_score');
            $this->insertGrade($base, ['score' => 150, 'max_score' => 100]);
            $this->fail('Expected score > max_score to fail');
        } catch (QueryException) {
            DB::statement('ROLLBACK TO SAVEPOINT sp_score');
            $this->assertTrue(true);
        }

        try {
            DB::statement('SAVEPOINT sp_absent');
            $this->insertGrade($base, ['score' => 95, 'is_absent' => true]);
            $this->fail('Expected absent+score to fail');
        } catch (QueryException) {
            DB::statement('ROLLBACK TO SAVEPOINT sp_absent');
            $this->assertTrue(true);
        }

        try {
            DB::statement('SAVEPOINT sp_max');
            $this->insertGrade($base, ['score' => 10, 'max_score' => 0]);
            $this->fail('Expected max_score <= 0 to fail');
        } catch (QueryException) {
            DB::statement('ROLLBACK TO SAVEPOINT sp_max');
            $this->assertTrue(true);
        }

        $id = $this->insertGrade($base, ['score' => 60]);

        try {
            DB::statement('SAVEPOINT sp_self');
            DB::table('exams.student_grades')
                ->where('id', $id)
                ->where('academic_year_id', $g['year_a'])
                ->update(['correction_of_grade_id' => $id, 'updated_at' => now()]);
            $this->fail('Expected self-correction to fail');
        } catch (QueryException) {
            DB::statement('ROLLBACK TO SAVEPOINT sp_self');
            $this->assertTrue(true);
        }

        $this->expectException(QueryException::class);
        DB::table('exams.student_grades')
            ->where('id', $id)
            ->where('academic_year_id', $g['year_a'])
            ->delete();
    }

    #[Test]
    public function cross_school_exam_enrollment_fk_is_rejected(): void
    {
        $g = $this->seedTwoSchoolGradeGraph();
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $g['school_a']]);

        $this->expectException(QueryException::class);
        $this->insertGrade([
            'year_id' => $g['year_a'],
            'school_id' => $g['school_a'],
            'exam_enrollment_id' => $g['exam_enrollment_b'],
            'session_id' => $g['session_a'],
            'enrollment_id' => $g['enrollment_a'],
            'student_id' => $g['student_a'],
            'subject_id' => $g['subject_id'],
        ]);
    }
}
