<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Application\Exams\Commands\EnterStudentGradeCommand;
use App\Application\Exams\Commands\EnterStudentGradeHandler;
use App\Application\Results\Commands\CalculateTermResultCommand;
use App\Application\Results\Commands\CalculateTermResultHandler;
use App\Database\SchemaHelper;
use App\Domain\Results\Exceptions\TermResultWeightException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Exams\SeedsApplicationExamGradeGraph;

final class Phase74CalculateTermResultPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;
    use SeedsApplicationExamGradeGraph;

    #[Test]
    public function calculate_term_result_persists_operational_version(): void
    {
        $schoolId = $this->createSchool('SCH-74-U02', 'U02 Results School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);

        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'U02');
        $this->forceExamTypeWeight($graph['exam_id'], 100);
        $this->markSessionInProgressForGradeEntry($graph['session_id']);

        $enter = $this->app->make(EnterStudentGradeHandler::class)->handle(new EnterStudentGradeCommand(
            schoolId: $schoolId,
            examEnrollmentId: $graph['exam_enrollment_id'],
            score: '85',
            isAbsent: false,
            enteredBy: (int) $user->id,
            idempotencyKey: 'u02-enter-85',
        ));
        $this->assertTrue($enter->success);

        $calc = $this->app->make(CalculateTermResultHandler::class)->handle(new CalculateTermResultCommand(
            schoolId: $schoolId,
            enrollmentId: $graph['enrollment_id'],
            academicYearId: $graph['year_id'],
            termId: $graph['term_id'],
            subjectId: $graph['subject_id'],
            idempotencyKey: 'u02-calc-1',
            createdBy: (int) $user->id,
        ));

        $this->assertTrue($calc->success);
        $this->assertSame(1, $calc->resultVersion);
        $this->assertSame('85.00', $calc->weightedTotal);
        $this->assertFalse($calc->incomplete);
        $this->assertDatabaseHas(SchemaHelper::qualified('results', 'term_results'), [
            'id' => $calc->termResultId,
            'school_id' => $schoolId,
            'enrollment_id' => $graph['enrollment_id'],
            'is_current_operational' => true,
            'is_official' => false,
            'lifecycle_status' => 1,
            'weighted_total' => '85.00',
        ]);
    }

    #[Test]
    public function calculate_is_idempotent_on_same_key(): void
    {
        $schoolId = $this->createSchool('SCH-74-U02B', 'U02 Idem School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);

        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'U02B');
        $this->forceExamTypeWeight($graph['exam_id'], 100);
        $this->markSessionInProgressForGradeEntry($graph['session_id']);

        $this->app->make(EnterStudentGradeHandler::class)->handle(new EnterStudentGradeCommand(
            schoolId: $schoolId,
            examEnrollmentId: $graph['exam_enrollment_id'],
            score: '70',
            isAbsent: false,
            enteredBy: (int) $user->id,
            idempotencyKey: 'u02b-enter',
        ));

        $handler = $this->app->make(CalculateTermResultHandler::class);
        $cmd = new CalculateTermResultCommand(
            schoolId: $schoolId,
            enrollmentId: $graph['enrollment_id'],
            academicYearId: $graph['year_id'],
            termId: $graph['term_id'],
            subjectId: $graph['subject_id'],
            idempotencyKey: 'u02b-calc',
            createdBy: (int) $user->id,
        );

        $first = $handler->handle($cmd);
        $second = $handler->handle($cmd);

        $this->assertTrue($second->fromIdempotencyCache);
        $this->assertSame($first->termResultId, $second->termResultId);
        $this->assertSame(1, (int) DB::table(SchemaHelper::qualified('results', 'term_results'))
            ->where('enrollment_id', $graph['enrollment_id'])
            ->where('subject_id', $graph['subject_id'])
            ->count());
    }

    #[Test]
    public function calculate_fails_closed_when_weights_invalid(): void
    {
        $schoolId = $this->createSchool('SCH-74-U02C', 'U02 Weight School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);

        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'U02C');
        // leave default weight 40
        $this->markSessionInProgressForGradeEntry($graph['session_id']);

        $this->app->make(EnterStudentGradeHandler::class)->handle(new EnterStudentGradeCommand(
            schoolId: $schoolId,
            examEnrollmentId: $graph['exam_enrollment_id'],
            score: '90',
            isAbsent: false,
            enteredBy: (int) $user->id,
            idempotencyKey: 'u02c-enter',
        ));

        $this->expectException(TermResultWeightException::class);

        $this->app->make(CalculateTermResultHandler::class)->handle(new CalculateTermResultCommand(
            schoolId: $schoolId,
            enrollmentId: $graph['enrollment_id'],
            academicYearId: $graph['year_id'],
            termId: $graph['term_id'],
            subjectId: $graph['subject_id'],
            idempotencyKey: 'u02c-calc',
            createdBy: (int) $user->id,
        ));
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
