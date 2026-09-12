<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Application\Exams\Commands\EnterStudentGradeCommand;
use App\Application\Exams\Commands\EnterStudentGradeHandler;
use App\Application\Exams\Commands\FinalizeStudentGradeCommand;
use App\Application\Exams\Commands\FinalizeStudentGradeHandler;
use App\Application\Results\Commands\FinalizeAnnualResultCommand;
use App\Application\Results\Commands\FinalizeAnnualResultHandler;
use App\Application\Results\Commands\FinalizeTermResultCommand;
use App\Application\Results\Commands\FinalizeTermResultHandler;
use App\Database\SchemaHelper;
use App\Domain\Results\Exceptions\AnnualResultNoTermResultsException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Exams\SeedsApplicationExamGradeGraph;

final class Phase74FinalizeAnnualResultPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;
    use SeedsApplicationExamGradeGraph;

    #[Test]
    public function finalize_annual_fails_without_official_term_rows(): void
    {
        $schoolId = $this->createSchool('SCH-74-U07A', 'Annual Fin A');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'U07A');

        $this->expectException(AnnualResultNoTermResultsException::class);

        $this->app->make(FinalizeAnnualResultHandler::class)->handle(new FinalizeAnnualResultCommand(
            schoolId: $schoolId,
            enrollmentId: $graph['enrollment_id'],
            academicYearId: $graph['year_id'],
            idempotencyKey: 'u07a-fail',
            createdBy: (int) $user->id,
        ));
    }

    #[Test]
    public function finalize_annual_persists_official_from_official_terms(): void
    {
        $schoolId = $this->createSchool('SCH-74-U07B', 'Annual Fin B');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);

        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'U07B');
        $typeId = (int) DB::table(SchemaHelper::qualified('exams', 'exams'))
            ->where('id', $graph['exam_id'])->value('exam_type_id');
        DB::table(SchemaHelper::qualified('exams', 'exam_types'))
            ->where('id', $typeId)->update(['weight_percentage' => 100]);
        $this->markSessionInProgressForGradeEntry($graph['session_id']);

        $enter = $this->app->make(EnterStudentGradeHandler::class)->handle(new EnterStudentGradeCommand(
            schoolId: $schoolId,
            examEnrollmentId: $graph['exam_enrollment_id'],
            score: '88',
            isAbsent: false,
            enteredBy: (int) $user->id,
            idempotencyKey: 'u07b-enter',
        ));
        $this->app->make(FinalizeStudentGradeHandler::class)->handle(new FinalizeStudentGradeCommand(
            schoolId: $schoolId,
            gradeId: (int) $enter->gradeId,
            academicYearId: $graph['year_id'],
            finalizedBy: (int) $user->id,
            idempotencyKey: 'u07b-gfin',
        ));
        $this->app->make(FinalizeTermResultHandler::class)->handle(new FinalizeTermResultCommand(
            schoolId: $schoolId,
            enrollmentId: $graph['enrollment_id'],
            academicYearId: $graph['year_id'],
            termId: $graph['term_id'],
            subjectId: $graph['subject_id'],
            idempotencyKey: 'u07b-tfin',
            createdBy: (int) $user->id,
        ));

        $annual = $this->app->make(FinalizeAnnualResultHandler::class)->handle(new FinalizeAnnualResultCommand(
            schoolId: $schoolId,
            enrollmentId: $graph['enrollment_id'],
            academicYearId: $graph['year_id'],
            idempotencyKey: 'u07b-afin',
            createdBy: (int) $user->id,
        ));

        $this->assertTrue($annual->success);
        $this->assertSame('88.00', $annual->averageWeightedTotal);
        $this->assertDatabaseHas(SchemaHelper::qualified('results', 'annual_results'), [
            'id' => $annual->annualResultId,
            'is_official' => true,
            'is_current_official' => true,
            'lifecycle_status' => 2,
        ]);
    }
}
