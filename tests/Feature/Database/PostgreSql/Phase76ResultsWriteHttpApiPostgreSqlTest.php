<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Application\Exams\Commands\EnterStudentGradeCommand;
use App\Application\Exams\Commands\EnterStudentGradeHandler;
use App\Application\Exams\Commands\FinalizeStudentGradeCommand;
use App\Application\Exams\Commands\FinalizeStudentGradeHandler;
use App\Database\SchemaHelper;
use App\Domain\Results\ValueObjects\GpaScope;
use App\Domain\Results\ValueObjects\ResultsLifecycleStatus;
use App\Security\Authorization\Permission;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Exams\SeedsApplicationExamGradeGraph;

final class Phase76ResultsWriteHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;
    use SeedsApplicationExamGradeGraph;

    #[Test]
    public function results_writer_permissions_are_registered(): void
    {
        $required = [
            Permission::RESULTS_CALCULATE,
            Permission::RESULTS_FINALIZE,
            Permission::RESULTS_RANKING_BUILD,
            Permission::RESULTS_TRANSCRIPT_ISSUE,
        ];

        foreach ($required as $permission) {
            $this->assertArrayHasKey($permission, config('security.permissions'));
            $this->assertContains($permission, Permission::all());
            $this->assertContains($permission, config('security.roles.results_manager'));
        }
    }

    #[Test]
    public function manager_can_calculate_and_finalize_term_via_http(): void
    {
        $schoolId = $this->createSchool('SCH-76-W01', 'Write HTTP');
        $gradesUser = $this->actingAsGradesManagerForSchool($schoolId);

        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'W01');
        $this->forceExamTypeWeight($graph['exam_id'], 100);
        $this->markSessionInProgressForGradeEntry($graph['session_id']);

        $enter = $this->app->make(EnterStudentGradeHandler::class)->handle(new EnterStudentGradeCommand(
            schoolId: $schoolId,
            examEnrollmentId: $graph['exam_enrollment_id'],
            score: '90',
            isAbsent: false,
            enteredBy: (int) $gradesUser->id,
            idempotencyKey: 'w01-enter',
        ));
        $this->assertTrue($enter->success);

        $this->app->make(FinalizeStudentGradeHandler::class)->handle(new FinalizeStudentGradeCommand(
            schoolId: $schoolId,
            gradeId: (int) $enter->gradeId,
            academicYearId: $graph['year_id'],
            finalizedBy: (int) $gradesUser->id,
            idempotencyKey: 'w01-grade-fin',
        ));

        $this->actingAsResultsManagerForSchool($schoolId);

        $calc = $this->postJson('/api/v1/results/term/calculate', [
            'enrollment_id' => $graph['enrollment_id'],
            'academic_year_id' => $graph['year_id'],
            'term_id' => $graph['term_id'],
            'subject_id' => $graph['subject_id'],
        ], [
            'X-Idempotency-Key' => 'w01-calc',
        ])->assertCreated();

        $termResultId = (int) $calc->json('data.id');
        $this->assertSame('90.00', $calc->json('data.weighted_total'));

        $this->postJson('/api/v1/results/term/calculate', [
            'enrollment_id' => $graph['enrollment_id'],
            'academic_year_id' => $graph['year_id'],
            'term_id' => $graph['term_id'],
            'subject_id' => $graph['subject_id'],
        ], [
            'X-Idempotency-Key' => 'w01-calc',
        ])->assertOk()
            ->assertJsonPath('data.id', $termResultId)
            ->assertJsonPath('meta.from_idempotency_cache', true);

        $fin = $this->postJson('/api/v1/results/term/finalize', [
            'enrollment_id' => $graph['enrollment_id'],
            'academic_year_id' => $graph['year_id'],
            'term_id' => $graph['term_id'],
            'subject_id' => $graph['subject_id'],
        ], [
            'X-Idempotency-Key' => 'w01-fin',
        ])->assertOk();

        $officialId = (int) $fin->json('data.id');
        $this->assertDatabaseHas(SchemaHelper::qualified('results', 'term_results'), [
            'id' => $officialId,
            'is_official' => true,
            'is_current_official' => true,
            'lifecycle_status' => ResultsLifecycleStatus::Finalized->value,
        ]);
    }

    #[Test]
    public function manager_can_issue_transcript_via_http(): void
    {
        $schoolId = $this->createSchool('SCH-76-W02', 'TR HTTP');
        $this->actingAsResultsManagerForSchool($schoolId);
        $enrollment = $this->createActiveEnrollmentForSchool($schoolId);

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
        DB::table(SchemaHelper::qualified('results', 'gpa_results'))->insert([
            'school_id' => $schoolId,
            'enrollment_id' => $enrollment->id,
            'student_id' => $enrollment->student_id,
            'academic_year_id' => $enrollment->academic_year_id,
            'gpa_scope' => GpaScope::AcademicYear->value,
            'result_version' => 1,
            'lifecycle_status' => ResultsLifecycleStatus::Finalized->value,
            'is_official' => true,
            'is_current_operational' => false,
            'is_current_official' => true,
            'gpa_value' => '91.50',
            'scale_code' => 'PERCENT_100',
            'source_annual_result_id' => null,
            'incomplete' => false,
            'source_fingerprint' => hash('sha256', 'w02-gpa'),
            'calculation_version' => 1,
            'policy_pin' => json_encode(['test' => true]),
            'calculated_at' => now(),
            'finalized_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $issued = $this->postJson('/api/v1/results/transcripts/issue', [
            'enrollment_id' => $enrollment->id,
            'academic_year_id' => $enrollment->academic_year_id,
        ], [
            'X-Idempotency-Key' => 'w02-issue',
        ])->assertCreated();

        $this->assertNotNull($issued->json('data.transcript_number'));
        $this->assertDatabaseHas(SchemaHelper::qualified('results', 'transcripts'), [
            'id' => (int) $issued->json('data.id'),
            'is_current' => true,
        ]);
    }

    #[Test]
    public function viewer_cannot_calculate_term(): void
    {
        $schoolId = $this->createSchool('SCH-76-W03', 'Deny write');
        $this->actingAsResultsViewerForSchool($schoolId);

        $this->postJson('/api/v1/results/term/calculate', [
            'enrollment_id' => 1,
            'academic_year_id' => 1,
            'term_id' => 1,
            'subject_id' => 1,
        ], [
            'X-Idempotency-Key' => 'w03-deny',
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
