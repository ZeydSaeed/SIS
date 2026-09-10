<?php

namespace Tests\Feature\Security\PostgreSql;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Database\PostgreSqlRlsActor;
use Tests\Support\Database\SeedsExamGradeGraph;

class StudentGradesRlsPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use SeedsExamGradeGraph;

    #[Test]
    public function school_context_isolates_grades_fail_closed(): void
    {
        $g = $this->seedTwoSchoolGradeGraph();

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $g['school_a']]);
        $gradeA = $this->insertGrade([
            'year_id' => $g['year_a'],
            'school_id' => $g['school_a'],
            'exam_enrollment_id' => $g['exam_enrollment_a'],
            'session_id' => $g['session_a'],
            'enrollment_id' => $g['enrollment_a'],
            'student_id' => $g['student_a'],
            'subject_id' => $g['subject_id'],
        ], ['score' => 66]);

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $g['school_b']]);
        $gradeB = $this->insertGrade([
            'year_id' => $g['year_b'],
            'school_id' => $g['school_b'],
            'exam_enrollment_id' => $g['exam_enrollment_b'],
            'session_id' => $g['session_b'],
            'enrollment_id' => $g['enrollment_b'],
            'student_id' => $g['student_b'],
            'subject_id' => $g['subject_id'],
        ], ['score' => 77]);

        PostgreSqlRlsActor::become();

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $g['school_a']]);
        $idsA = collect(DB::select('SELECT id FROM exams.student_grades'))->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assertSame([$gradeA], $idsA);
        $this->assertNotContains($gradeB, $idsA);

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $g['school_b']]);
        $idsB = collect(DB::select('SELECT id FROM exams.student_grades'))->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assertSame([$gradeB], $idsB);

        DB::statement("SELECT set_config('app.current_school_id', '', true)");
        $this->assertSame(0, (int) DB::selectOne('SELECT COUNT(*) AS c FROM exams.student_grades')->c);

        DB::statement("SELECT set_config('app.current_school_id', '999999', true)");
        $this->assertSame(0, (int) DB::selectOne('SELECT COUNT(*) AS c FROM exams.student_grades')->c);

        PostgreSqlRlsActor::reset();
    }

    #[Test]
    public function rls_actor_cannot_insert_for_other_school(): void
    {
        $g = $this->seedTwoSchoolGradeGraph();

        PostgreSqlRlsActor::become();
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $g['school_a']]);

        $this->expectException(QueryException::class);
        DB::table('exams.student_grades')->insert([
            'academic_year_id' => $g['year_b'],
            'school_id' => $g['school_b'],
            'exam_enrollment_id' => $g['exam_enrollment_b'],
            'exam_session_id' => $g['session_b'],
            'enrollment_id' => $g['enrollment_b'],
            'student_id' => $g['student_b'],
            'subject_id' => $g['subject_id'],
            'score' => 55,
            'max_score' => 100,
            'is_absent' => false,
            'status' => 2,
            'is_current' => true,
            'entered_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
