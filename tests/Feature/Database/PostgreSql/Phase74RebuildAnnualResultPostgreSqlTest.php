<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Application\Exams\Commands\CorrectStudentGradeCommand;
use App\Application\Exams\Commands\CorrectStudentGradeHandler;
use App\Application\Exams\Commands\EnterStudentGradeCommand;
use App\Application\Exams\Commands\EnterStudentGradeHandler;
use App\Application\Results\Commands\CalculateAnnualResultCommand;
use App\Application\Results\Commands\CalculateAnnualResultHandler;
use App\Application\Results\Commands\CalculateTermResultCommand;
use App\Application\Results\Commands\CalculateTermResultHandler;
use App\Application\Results\Commands\RebuildAnnualResultCommand;
use App\Application\Results\Commands\RebuildAnnualResultHandler;
use App\Application\Results\Commands\RebuildTermResultCommand;
use App\Application\Results\Commands\RebuildTermResultHandler;
use App\Database\SchemaHelper;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Exams\SeedsApplicationExamGradeGraph;

final class Phase74RebuildAnnualResultPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;
    use SeedsApplicationExamGradeGraph;

    #[Test]
    public function rebuild_annual_operational_unchanged_when_fingerprint_matches(): void
    {
        [$schoolId, $user, $graph] = $this->seedWithTermCalc('U08A');

        $annual = $this->app->make(CalculateAnnualResultHandler::class)->handle(new CalculateAnnualResultCommand(
            schoolId: $schoolId,
            enrollmentId: $graph['enrollment_id'],
            academicYearId: $graph['year_id'],
            idempotencyKey: 'u08a-annual',
            createdBy: (int) $user->id,
        ));

        $rebuild = $this->app->make(RebuildAnnualResultHandler::class)->handle(new RebuildAnnualResultCommand(
            schoolId: $schoolId,
            enrollmentId: $graph['enrollment_id'],
            academicYearId: $graph['year_id'],
            mode: 'operational',
            idempotencyKey: 'u08a-rebuild',
            createdBy: (int) $user->id,
        ));

        $this->assertTrue($rebuild->unchanged);
        $this->assertSame($annual->annualResultId, $rebuild->annualResultId);
        $this->assertSame(1, (int) DB::table(SchemaHelper::qualified('results', 'annual_results'))
            ->where('enrollment_id', $graph['enrollment_id'])
            ->count());
    }

    #[Test]
    public function rebuild_annual_creates_new_version_after_term_rebuild(): void
    {
        [$schoolId, $user, $graph, $gradeId] = $this->seedWithTermCalc('U08B', withGradeId: true);

        $this->app->make(CalculateAnnualResultHandler::class)->handle(new CalculateAnnualResultCommand(
            schoolId: $schoolId,
            enrollmentId: $graph['enrollment_id'],
            academicYearId: $graph['year_id'],
            idempotencyKey: 'u08b-annual',
            createdBy: (int) $user->id,
        ));

        $this->app->make(CorrectStudentGradeHandler::class)->handle(new CorrectStudentGradeCommand(
            schoolId: $schoolId,
            gradeId: $gradeId,
            academicYearId: $graph['year_id'],
            score: '95',
            isAbsent: false,
            reason: 'u08b correction',
            correctedBy: (int) $user->id,
            idempotencyKey: 'u08b-correct',
        ));

        $this->app->make(RebuildTermResultHandler::class)->handle(new RebuildTermResultCommand(
            schoolId: $schoolId,
            enrollmentId: $graph['enrollment_id'],
            academicYearId: $graph['year_id'],
            termId: $graph['term_id'],
            subjectId: $graph['subject_id'],
            mode: 'operational',
            idempotencyKey: 'u08b-term-rebuild',
            createdBy: (int) $user->id,
        ));

        $rebuild = $this->app->make(RebuildAnnualResultHandler::class)->handle(new RebuildAnnualResultCommand(
            schoolId: $schoolId,
            enrollmentId: $graph['enrollment_id'],
            academicYearId: $graph['year_id'],
            mode: 'operational',
            idempotencyKey: 'u08b-annual-rebuild',
            createdBy: (int) $user->id,
        ));

        $this->assertFalse($rebuild->unchanged);
        $this->assertSame(2, $rebuild->resultVersion);
        $this->assertSame('95.00', $rebuild->averageWeightedTotal);
    }

    /**
     * @return array{0:int,1:object,2:array<string,mixed>,3?:int}
     */
    private function seedWithTermCalc(string $suffix, bool $withGradeId = false): array
    {
        $schoolId = $this->createSchool('SCH-74-'.$suffix, 'Annual Rebuild '.$suffix);
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);

        $graph = $this->seedExamGradeGraph($schoolId, suffix: $suffix);
        $typeId = (int) DB::table(SchemaHelper::qualified('exams', 'exams'))
            ->where('id', $graph['exam_id'])->value('exam_type_id');
        DB::table(SchemaHelper::qualified('exams', 'exam_types'))
            ->where('id', $typeId)->update(['weight_percentage' => 100]);
        $this->markSessionInProgressForGradeEntry($graph['session_id']);

        $enter = $this->app->make(EnterStudentGradeHandler::class)->handle(new EnterStudentGradeCommand(
            schoolId: $schoolId,
            examEnrollmentId: $graph['exam_enrollment_id'],
            score: '70',
            isAbsent: false,
            enteredBy: (int) $user->id,
            idempotencyKey: 'enter-'.$suffix,
        ));

        $this->app->make(CalculateTermResultHandler::class)->handle(new CalculateTermResultCommand(
            schoolId: $schoolId,
            enrollmentId: $graph['enrollment_id'],
            academicYearId: $graph['year_id'],
            termId: $graph['term_id'],
            subjectId: $graph['subject_id'],
            idempotencyKey: 'term-'.$suffix,
            createdBy: (int) $user->id,
        ));

        if ($withGradeId) {
            return [$schoolId, $user, $graph, (int) $enter->gradeId];
        }

        return [$schoolId, $user, $graph];
    }
}
