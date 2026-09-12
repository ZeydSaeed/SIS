<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Application\Exams\Commands\EnterStudentGradeCommand;
use App\Application\Exams\Commands\EnterStudentGradeHandler;
use App\Application\Exams\Commands\FinalizeStudentGradeCommand;
use App\Application\Exams\Commands\FinalizeStudentGradeHandler;
use App\Application\Results\Commands\FinalizeTermResultCommand;
use App\Application\Results\Commands\FinalizeTermResultHandler;
use App\Database\SchemaHelper;
use App\Domain\Results\Exceptions\TermResultDatasetIncompleteException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Exams\SeedsApplicationExamGradeGraph;

final class Phase74FinalizeTermResultPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;
    use SeedsApplicationExamGradeGraph;

    #[Test]
    public function finalize_term_result_requires_finalized_grades(): void
    {
        $schoolId = $this->createSchool('SCH-74-U03A', 'U03 Incomplete');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);

        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'U03A');
        $this->forceExamTypeWeight($graph['exam_id'], 100);
        $this->markSessionInProgressForGradeEntry($graph['session_id']);

        $enter = $this->app->make(EnterStudentGradeHandler::class)->handle(new EnterStudentGradeCommand(
            schoolId: $schoolId,
            examEnrollmentId: $graph['exam_enrollment_id'],
            score: '88',
            isAbsent: false,
            enteredBy: (int) $user->id,
            idempotencyKey: 'u03a-enter',
        ));
        $this->assertTrue($enter->success);

        $this->expectException(TermResultDatasetIncompleteException::class);

        $this->app->make(FinalizeTermResultHandler::class)->handle(new FinalizeTermResultCommand(
            schoolId: $schoolId,
            enrollmentId: $graph['enrollment_id'],
            academicYearId: $graph['year_id'],
            termId: $graph['term_id'],
            subjectId: $graph['subject_id'],
            idempotencyKey: 'u03a-fin',
            createdBy: (int) $user->id,
        ));
    }

    #[Test]
    public function finalize_term_result_persists_official_current(): void
    {
        $schoolId = $this->createSchool('SCH-74-U03B', 'U03 Official');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);

        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'U03B');
        $this->forceExamTypeWeight($graph['exam_id'], 100);
        $this->markSessionInProgressForGradeEntry($graph['session_id']);

        $enter = $this->app->make(EnterStudentGradeHandler::class)->handle(new EnterStudentGradeCommand(
            schoolId: $schoolId,
            examEnrollmentId: $graph['exam_enrollment_id'],
            score: '92',
            isAbsent: false,
            enteredBy: (int) $user->id,
            idempotencyKey: 'u03b-enter',
        ));

        $this->app->make(FinalizeStudentGradeHandler::class)->handle(new FinalizeStudentGradeCommand(
            schoolId: $schoolId,
            gradeId: (int) $enter->gradeId,
            academicYearId: $graph['year_id'],
            finalizedBy: (int) $user->id,
            idempotencyKey: 'u03b-grade-fin',
        ));

        $fin = $this->app->make(FinalizeTermResultHandler::class)->handle(new FinalizeTermResultCommand(
            schoolId: $schoolId,
            enrollmentId: $graph['enrollment_id'],
            academicYearId: $graph['year_id'],
            termId: $graph['term_id'],
            subjectId: $graph['subject_id'],
            idempotencyKey: 'u03b-term-fin',
            createdBy: (int) $user->id,
        ));

        $this->assertTrue($fin->success);
        $this->assertSame('92.00', $fin->weightedTotal);
        $this->assertDatabaseHas(SchemaHelper::qualified('results', 'term_results'), [
            'id' => $fin->termResultId,
            'is_official' => true,
            'is_current_official' => true,
            'lifecycle_status' => 2,
            'weighted_total' => '92.00',
        ]);
    }

    private function forceExamTypeWeight(int $examId, int $weight): void
    {
        $typeId = (int) DB::table(SchemaHelper::qualified('exams', 'exams'))
            ->where('id', $examId)
            ->value('exam_type_id');

        DB::table(SchemaHelper::qualified('exams', 'exam_types'))
            ->where('id', $typeId)
            ->update(['weight_percentage' => $weight]);
    }
}
