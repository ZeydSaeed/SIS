<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Application\Exams\Commands\EnterStudentGradeCommand;
use App\Application\Exams\Commands\EnterStudentGradeHandler;
use App\Application\Results\Commands\CalculateTermResultCommand;
use App\Application\Results\Commands\CalculateTermResultHandler;
use App\Database\SchemaHelper;
use App\Domain\Results\ValueObjects\TermResultRebuildMode;
use App\Security\Authorization\Permission;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Exams\SeedsApplicationExamGradeGraph;

final class Phase76ResultsRebuildHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;
    use SeedsApplicationExamGradeGraph;

    #[Test]
    public function results_rebuild_permission_is_registered(): void
    {
        $this->assertArrayHasKey(Permission::RESULTS_REBUILD, config('security.permissions'));
        $this->assertContains(Permission::RESULTS_REBUILD, Permission::all());
        $this->assertContains(Permission::RESULTS_REBUILD, config('security.roles.results_manager'));
    }

    #[Test]
    public function manager_can_rebuild_operational_term_via_http(): void
    {
        $schoolId = $this->createSchool('SCH-76-RB1', 'Rebuild HTTP');
        $gradesUser = $this->actingAsGradesManagerForSchool($schoolId);

        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'RB1');
        $this->forceExamTypeWeight($graph['exam_id'], 100);
        $this->markSessionInProgressForGradeEntry($graph['session_id']);

        $this->app->make(EnterStudentGradeHandler::class)->handle(new EnterStudentGradeCommand(
            schoolId: $schoolId,
            examEnrollmentId: $graph['exam_enrollment_id'],
            score: '80',
            isAbsent: false,
            enteredBy: (int) $gradesUser->id,
            idempotencyKey: 'rb1-enter',
        ));

        $calc = $this->app->make(CalculateTermResultHandler::class)->handle(new CalculateTermResultCommand(
            schoolId: $schoolId,
            enrollmentId: $graph['enrollment_id'],
            academicYearId: $graph['year_id'],
            termId: $graph['term_id'],
            subjectId: $graph['subject_id'],
            idempotencyKey: 'rb1-calc',
            createdBy: (int) $gradesUser->id,
        ));

        $this->actingAsResultsManagerForSchool($schoolId);

        $rebuild = $this->postJson('/api/v1/results/term/rebuild', [
            'enrollment_id' => $graph['enrollment_id'],
            'academic_year_id' => $graph['year_id'],
            'term_id' => $graph['term_id'],
            'subject_id' => $graph['subject_id'],
            'mode' => TermResultRebuildMode::Operational->value,
        ], [
            'X-Idempotency-Key' => 'rb1-rebuild',
        ])->assertOk();

        $this->assertTrue((bool) $rebuild->json('data.unchanged'));
        $this->assertSame($calc->termResultId, (int) $rebuild->json('data.id'));
        $this->assertSame(TermResultRebuildMode::Operational->value, $rebuild->json('data.mode'));
        $this->assertSame(1, (int) DB::table(SchemaHelper::qualified('results', 'term_results'))
            ->where('enrollment_id', $graph['enrollment_id'])
            ->where('subject_id', $graph['subject_id'])
            ->count());
    }

    #[Test]
    public function rebuild_rejects_invalid_mode(): void
    {
        $schoolId = $this->createSchool('SCH-76-RB2', 'Rebuild mode');
        $this->actingAsResultsManagerForSchool($schoolId);

        $this->postJson('/api/v1/results/term/rebuild', [
            'enrollment_id' => 1,
            'academic_year_id' => 1,
            'term_id' => 1,
            'subject_id' => 1,
            'mode' => 'silent',
        ], [
            'X-Idempotency-Key' => 'rb2-bad-mode',
        ])->assertStatus(422);
    }

    #[Test]
    public function viewer_cannot_rebuild(): void
    {
        $schoolId = $this->createSchool('SCH-76-RB3', 'Rebuild deny');
        $this->actingAsResultsViewerForSchool($schoolId);

        $this->postJson('/api/v1/results/gpa/rebuild', [
            'enrollment_id' => 1,
            'academic_year_id' => 1,
            'mode' => TermResultRebuildMode::Operational->value,
        ], [
            'X-Idempotency-Key' => 'rb3-deny',
        ])->assertForbidden();
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
