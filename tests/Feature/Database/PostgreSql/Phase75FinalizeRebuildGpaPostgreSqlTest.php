<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Application\Exams\Commands\EnterStudentGradeCommand;
use App\Application\Exams\Commands\EnterStudentGradeHandler;
use App\Application\Exams\Commands\FinalizeStudentGradeCommand;
use App\Application\Exams\Commands\FinalizeStudentGradeHandler;
use App\Application\Results\Commands\CalculateGpaCommand;
use App\Application\Results\Commands\CalculateGpaHandler;
use App\Application\Results\Commands\FinalizeAnnualResultCommand;
use App\Application\Results\Commands\FinalizeAnnualResultHandler;
use App\Application\Results\Commands\FinalizeGpaCommand;
use App\Application\Results\Commands\FinalizeGpaHandler;
use App\Application\Results\Commands\FinalizeTermResultCommand;
use App\Application\Results\Commands\FinalizeTermResultHandler;
use App\Application\Results\Commands\RebuildGpaCommand;
use App\Application\Results\Commands\RebuildGpaHandler;
use App\Database\SchemaHelper;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Exams\SeedsApplicationExamGradeGraph;

final class Phase75FinalizeRebuildGpaPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;
    use SeedsApplicationExamGradeGraph;

    #[Test]
    public function finalize_gpa_persists_official_current(): void
    {
        [$schoolId, $user, $graph] = $this->seedOfficialAnnualPath('75F');

        $fin = $this->app->make(FinalizeGpaHandler::class)->handle(new FinalizeGpaCommand(
            schoolId: $schoolId,
            enrollmentId: $graph['enrollment_id'],
            academicYearId: $graph['year_id'],
            idempotencyKey: '75f-gpa-fin',
            createdBy: (int) $user->id,
        ));

        $this->assertTrue($fin->success);
        $this->assertSame('84.00', $fin->gpaValue);
        $this->assertDatabaseHas(SchemaHelper::qualified('results', 'gpa_results'), [
            'id' => $fin->gpaResultId,
            'is_official' => true,
            'is_current_official' => true,
            'lifecycle_status' => 2,
        ]);
    }

    #[Test]
    public function rebuild_gpa_operational_unchanged_when_fingerprint_matches(): void
    {
        [$schoolId, $user, $graph] = $this->seedOfficialAnnualPath('75R');

        $calc = $this->app->make(CalculateGpaHandler::class)->handle(new CalculateGpaCommand(
            schoolId: $schoolId,
            enrollmentId: $graph['enrollment_id'],
            academicYearId: $graph['year_id'],
            idempotencyKey: '75r-calc',
            createdBy: (int) $user->id,
        ));

        $rebuild = $this->app->make(RebuildGpaHandler::class)->handle(new RebuildGpaCommand(
            schoolId: $schoolId,
            enrollmentId: $graph['enrollment_id'],
            academicYearId: $graph['year_id'],
            mode: 'operational',
            idempotencyKey: '75r-rebuild',
            createdBy: (int) $user->id,
        ));

        $this->assertTrue($rebuild->unchanged);
        $this->assertSame($calc->gpaResultId, $rebuild->gpaResultId);
    }

    /**
     * @return array{0:int,1:object,2:array<string,mixed>}
     */
    private function seedOfficialAnnualPath(string $suffix): array
    {
        $schoolId = $this->createSchool('SCH-75-'.$suffix, 'GPA '.$suffix);
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
            score: '84',
            isAbsent: false,
            enteredBy: (int) $user->id,
            idempotencyKey: 'enter-'.$suffix,
        ));
        $this->app->make(FinalizeStudentGradeHandler::class)->handle(new FinalizeStudentGradeCommand(
            schoolId: $schoolId,
            gradeId: (int) $enter->gradeId,
            academicYearId: $graph['year_id'],
            finalizedBy: (int) $user->id,
            idempotencyKey: 'gfin-'.$suffix,
        ));
        $this->app->make(FinalizeTermResultHandler::class)->handle(new FinalizeTermResultCommand(
            schoolId: $schoolId,
            enrollmentId: $graph['enrollment_id'],
            academicYearId: $graph['year_id'],
            termId: $graph['term_id'],
            subjectId: $graph['subject_id'],
            idempotencyKey: 'tfin-'.$suffix,
            createdBy: (int) $user->id,
        ));
        $this->app->make(FinalizeAnnualResultHandler::class)->handle(new FinalizeAnnualResultCommand(
            schoolId: $schoolId,
            enrollmentId: $graph['enrollment_id'],
            academicYearId: $graph['year_id'],
            idempotencyKey: 'afin-'.$suffix,
            createdBy: (int) $user->id,
        ));

        return [$schoolId, $user, $graph];
    }
}
