<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Results\ValueObjects\ResultsLifecycleStatus;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseResListOfficialTermResultsHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function viewer_can_list_official_term_results_for_enrollment_year(): void
    {
        $schoolId = $this->createSchool('SCH-RES-LT', 'List Terms');
        $yearId = $this->createAcademicYear();
        $enrollment = $this->createActiveEnrollmentForSchool($schoolId, $yearId);
        $termId = (int) DB::table(SchemaHelper::qualified('academic', 'terms'))->insertGetId([
            'academic_year_id' => $yearId,
            'code' => 'T1-LT',
            'name' => 'Term 1',
            'term_order' => 1,
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-31',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $subjectId = (int) DB::table(SchemaHelper::qualified('curriculum', 'subjects'))->insertGetId([
            'code' => 'S'.substr(uniqid(), -8),
            'name' => 'Math',
            'subject_type' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);

        $termResultId = (int) DB::table(SchemaHelper::qualified('results', 'term_results'))->insertGetId([
            'school_id' => $schoolId,
            'enrollment_id' => $enrollment->id,
            'student_id' => $enrollment->student_id,
            'academic_year_id' => $yearId,
            'term_id' => $termId,
            'subject_id' => $subjectId,
            'result_version' => 1,
            'lifecycle_status' => ResultsLifecycleStatus::Finalized->value,
            'is_official' => true,
            'is_current_operational' => false,
            'is_current_official' => true,
            'weighted_total' => '91.50',
            'pass_fail' => 1,
            'incomplete' => false,
            'source_fingerprint' => hash('sha256', 'res-list-term'),
            'calculation_version' => 1,
            'policy_pin' => json_encode(['test' => true]),
            'calculated_at' => now(),
            'finalized_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAsResultsViewerForSchool($schoolId);

        $this->getJson(sprintf(
            '/api/v1/results/terms?enrollment_id=%d&academic_year_id=%d',
            $enrollment->id,
            $yearId,
        ))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.term_result_id', $termResultId)
            ->assertJsonPath('data.0.term_id', $termId)
            ->assertJsonPath('data.0.subject_id', $subjectId)
            ->assertJsonPath('data.0.weighted_total', '91.50')
            ->assertJsonPath('data.0.pass_fail', 1)
            ->assertJsonPath('data.0.incomplete', false);
    }
}
