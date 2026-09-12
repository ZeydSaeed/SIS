<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Application\Exams\Commands\CorrectStudentGradeCommand;
use App\Application\Exams\Commands\CorrectStudentGradeHandler;
use App\Application\Exams\Commands\EnterStudentGradeCommand;
use App\Application\Exams\Commands\EnterStudentGradeHandler;
use App\Application\Results\Commands\CalculateTermResultCommand;
use App\Application\Results\Commands\CalculateTermResultHandler;
use App\Application\Results\Commands\RebuildTermResultCommand;
use App\Application\Results\Commands\RebuildTermResultHandler;
use App\Database\SchemaHelper;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Exams\SeedsApplicationExamGradeGraph;

final class Phase74RebuildTermResultPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;
    use SeedsApplicationExamGradeGraph;

    #[Test]
    public function rebuild_operational_is_unchanged_when_fingerprint_matches(): void
    {
        [$schoolId, $user, $graph] = $this->seedReadyGraph('U04A');

        $calc = $this->app->make(CalculateTermResultHandler::class)->handle(new CalculateTermResultCommand(
            schoolId: $schoolId,
            enrollmentId: $graph['enrollment_id'],
            academicYearId: $graph['year_id'],
            termId: $graph['term_id'],
            subjectId: $graph['subject_id'],
            idempotencyKey: 'u04a-calc',
            createdBy: (int) $user->id,
        ));

        $rebuild = $this->app->make(RebuildTermResultHandler::class)->handle(new RebuildTermResultCommand(
            schoolId: $schoolId,
            enrollmentId: $graph['enrollment_id'],
            academicYearId: $graph['year_id'],
            termId: $graph['term_id'],
            subjectId: $graph['subject_id'],
            mode: 'operational',
            idempotencyKey: 'u04a-rebuild',
            createdBy: (int) $user->id,
        ));

        $this->assertTrue($rebuild->unchanged);
        $this->assertSame($calc->termResultId, $rebuild->termResultId);
        $this->assertSame(1, (int) DB::table(SchemaHelper::qualified('results', 'term_results'))
            ->where('enrollment_id', $graph['enrollment_id'])
            ->where('subject_id', $graph['subject_id'])
            ->count());
    }

    #[Test]
    public function rebuild_operational_creates_new_version_when_grades_change(): void
    {
        [$schoolId, $user, $graph, $gradeId] = $this->seedReadyGraph('U04B', withGradeId: true);

        $this->app->make(CalculateTermResultHandler::class)->handle(new CalculateTermResultCommand(
            schoolId: $schoolId,
            enrollmentId: $graph['enrollment_id'],
            academicYearId: $graph['year_id'],
            termId: $graph['term_id'],
            subjectId: $graph['subject_id'],
            idempotencyKey: 'u04b-calc',
            createdBy: (int) $user->id,
        ));

        $this->app->make(CorrectStudentGradeHandler::class)->handle(new CorrectStudentGradeCommand(
            schoolId: $schoolId,
            gradeId: $gradeId,
            academicYearId: $graph['year_id'],
            score: '90',
            isAbsent: false,
            reason: 'rebuild test correction',
            correctedBy: (int) $user->id,
            idempotencyKey: 'u04b-correct',
        ));

        $rebuild = $this->app->make(RebuildTermResultHandler::class)->handle(new RebuildTermResultCommand(
            schoolId: $schoolId,
            enrollmentId: $graph['enrollment_id'],
            academicYearId: $graph['year_id'],
            termId: $graph['term_id'],
            subjectId: $graph['subject_id'],
            mode: 'operational',
            idempotencyKey: 'u04b-rebuild',
            createdBy: (int) $user->id,
        ));

        $this->assertFalse($rebuild->unchanged);
        $this->assertSame(2, $rebuild->resultVersion);
        $this->assertSame('90.00', $rebuild->weightedTotal);
        $this->assertSame(2, (int) DB::table(SchemaHelper::qualified('results', 'term_results'))
            ->where('enrollment_id', $graph['enrollment_id'])
            ->where('subject_id', $graph['subject_id'])
            ->count());
    }

    /**
     * @return array{0:int,1:object,2:array<string,mixed>,3?:int}
     */
    private function seedReadyGraph(string $suffix, bool $withGradeId = false): array
    {
        $schoolId = $this->createSchool('SCH-74-'.$suffix, 'Rebuild '.$suffix);
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);

        $graph = $this->seedExamGradeGraph($schoolId, suffix: $suffix);
        $this->forceExamTypeWeight($graph['exam_id'], 100);
        $this->markSessionInProgressForGradeEntry($graph['session_id']);

        $enter = $this->app->make(EnterStudentGradeHandler::class)->handle(new EnterStudentGradeCommand(
            schoolId: $schoolId,
            examEnrollmentId: $graph['exam_enrollment_id'],
            score: '75',
            isAbsent: false,
            enteredBy: (int) $user->id,
            idempotencyKey: 'enter-'.$suffix,
        ));

        if ($withGradeId) {
            return [$schoolId, $user, $graph, (int) $enter->gradeId];
        }

        return [$schoolId, $user, $graph];
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
