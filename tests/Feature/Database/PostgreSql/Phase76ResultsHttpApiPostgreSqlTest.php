<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Application\Results\Queries\GetCurrentRankingSnapshotHandler;
use App\Database\SchemaHelper;
use App\Domain\Results\ValueObjects\GpaScope;
use App\Domain\Results\ValueObjects\ResultsLifecycleStatus;
use App\Security\Authorization\Permission;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class Phase76ResultsHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function results_view_permission_is_registered(): void
    {
        $this->assertArrayHasKey(Permission::RESULTS_VIEW, config('security.permissions'));
        $this->assertContains(Permission::RESULTS_VIEW, Permission::all());
        $this->assertContains(Permission::RESULTS_VIEW, config('security.roles.results_viewer'));
    }

    #[Test]
    public function viewer_can_read_official_results_surfaces(): void
    {
        $seed = $this->seedOfficialResults('HTTP');
        $this->actingAsResultsViewerForSchool($seed['school_id']);

        $this->getJson(sprintf(
            '/api/v1/results/term?enrollment_id=%d&academic_year_id=%d&term_id=%d&subject_id=%d',
            $seed['enrollment_id'],
            $seed['year_id'],
            $seed['term_id'],
            $seed['subject_id'],
        ))->assertOk()
            ->assertJsonPath('data.term_result_id', $seed['term_result_id'])
            ->assertJsonPath('data.weighted_total', '88.00');

        $this->getJson(sprintf(
            '/api/v1/results/annual?enrollment_id=%d&academic_year_id=%d',
            $seed['enrollment_id'],
            $seed['year_id'],
        ))->assertOk()
            ->assertJsonPath('data.annual_result_id', $seed['annual_id']);

        $this->getJson(sprintf(
            '/api/v1/results/gpa?enrollment_id=%d&academic_year_id=%d',
            $seed['enrollment_id'],
            $seed['year_id'],
        ))->assertOk()
            ->assertJsonPath('data.gpa_result_id', $seed['gpa_id'])
            ->assertJsonPath('data.scale_code', 'PERCENT_100');

        $this->getJson(sprintf(
            '/api/v1/results/ranking?academic_year_id=%d&class_id=%d',
            $seed['year_id'],
            $seed['class_id'],
        ))->assertOk()
            ->assertJsonPath('data.ranking_snapshot_id', $seed['snapshot_id'])
            ->assertJsonPath(
                'data.comparative_projection_label',
                GetCurrentRankingSnapshotHandler::COMPARATIVE_LABEL,
            )
            ->assertJsonPath('data.entries.0.rank_position', 1);

        $this->getJson(sprintf(
            '/api/v1/results/transcripts/issued?enrollment_id=%d&academic_year_id=%d',
            $seed['enrollment_id'],
            $seed['year_id'],
        ))->assertOk()
            ->assertJsonPath('data.transcript_id', $seed['transcript_id']);
    }

    #[Test]
    public function missing_official_term_returns_404(): void
    {
        $schoolId = $this->createSchool('SCH-76-H404', 'HTTP 404');
        $yearId = $this->createAcademicYear();
        $enrollment = $this->createActiveEnrollmentForSchool($schoolId, $yearId);
        $this->actingAsResultsViewerForSchool($schoolId);

        $this->getJson(sprintf(
            '/api/v1/results/term?enrollment_id=%d&academic_year_id=%d&term_id=999999&subject_id=999999',
            $enrollment->id,
            $yearId,
        ))->assertNotFound()
            ->assertJsonPath('error_code', 'results.term_not_found');
    }

    #[Test]
    public function unauthorized_user_cannot_view_results(): void
    {
        $seed = $this->seedOfficialResults('DENY');
        $this->actingAsAttendanceViewer(schoolId: $seed['school_id']);

        $this->getJson(sprintf(
            '/api/v1/results/annual?enrollment_id=%d&academic_year_id=%d',
            $seed['enrollment_id'],
            $seed['year_id'],
        ))->assertForbidden();
    }

    /**
     * @return array{
     *     school_id:int,
     *     year_id:int,
     *     enrollment_id:int,
     *     class_id:int,
     *     term_id:int,
     *     subject_id:int,
     *     term_result_id:int,
     *     annual_id:int,
     *     gpa_id:int,
     *     snapshot_id:int,
     *     transcript_id:int
     * }
     */
    private function seedOfficialResults(string $suffix): array
    {
        $schoolId = $this->createSchool('SCH-76-'.$suffix, 'Read '.$suffix);
        $yearId = $this->createAcademicYear();
        $enrollment = $this->createActiveEnrollmentForSchool($schoolId, $yearId);
        $termId = (int) DB::table(SchemaHelper::qualified('academic', 'terms'))->insertGetId([
            'academic_year_id' => $yearId,
            'code' => 'T1-'.$suffix,
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
            'weighted_total' => '88.00',
            'pass_fail' => 1,
            'incomplete' => false,
            'source_fingerprint' => hash('sha256', '76-http-term'),
            'calculation_version' => 1,
            'policy_pin' => json_encode(['test' => true]),
            'calculated_at' => now(),
            'finalized_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $annualId = (int) DB::table(SchemaHelper::qualified('results', 'annual_results'))->insertGetId([
            'school_id' => $schoolId,
            'enrollment_id' => $enrollment->id,
            'student_id' => $enrollment->student_id,
            'academic_year_id' => $yearId,
            'result_version' => 1,
            'lifecycle_status' => ResultsLifecycleStatus::Finalized->value,
            'is_official' => true,
            'is_current_operational' => false,
            'is_current_official' => true,
            'average_weighted_total' => '88.00',
            'incomplete' => false,
            'source_fingerprint' => hash('sha256', '76-http-annual'),
            'calculation_version' => 1,
            'policy_pin' => json_encode(['test' => true]),
            'calculated_at' => now(),
            'finalized_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $gpaId = (int) DB::table(SchemaHelper::qualified('results', 'gpa_results'))->insertGetId([
            'school_id' => $schoolId,
            'enrollment_id' => $enrollment->id,
            'student_id' => $enrollment->student_id,
            'academic_year_id' => $yearId,
            'gpa_scope' => GpaScope::AcademicYear->value,
            'result_version' => 1,
            'lifecycle_status' => ResultsLifecycleStatus::Finalized->value,
            'is_official' => true,
            'is_current_operational' => false,
            'is_current_official' => true,
            'gpa_value' => '88.00',
            'scale_code' => 'PERCENT_100',
            'source_annual_result_id' => $annualId,
            'incomplete' => false,
            'source_fingerprint' => hash('sha256', '76-http-gpa'),
            'calculation_version' => 1,
            'policy_pin' => json_encode(['test' => true]),
            'calculated_at' => now(),
            'finalized_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $snapshotId = (int) DB::table(SchemaHelper::qualified('results', 'ranking_snapshots'))->insertGetId([
            'school_id' => $schoolId,
            'academic_year_id' => $yearId,
            'class_id' => $enrollment->class_id,
            'snapshot_version' => 1,
            'lifecycle_status' => ResultsLifecycleStatus::Calculated->value,
            'is_current' => true,
            'metric_code' => 'YEAR_GPA_PERCENT_100',
            'participant_count' => 1,
            'source_fingerprint' => hash('sha256', '76-http-rank'),
            'policy_pin' => json_encode(['test' => true]),
            'calculated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table(SchemaHelper::qualified('results', 'ranking_snapshot_entries'))->insert([
            'ranking_snapshot_id' => $snapshotId,
            'school_id' => $schoolId,
            'enrollment_id' => $enrollment->id,
            'student_id' => $enrollment->student_id,
            'gpa_result_id' => $gpaId,
            'metric_value' => '88.00',
            'rank_position' => 1,
            'created_at' => now(),
        ]);

        $transcriptId = (int) DB::table(SchemaHelper::qualified('results', 'transcripts'))->insertGetId([
            'school_id' => $schoolId,
            'student_id' => $enrollment->student_id,
            'enrollment_id' => $enrollment->id,
            'academic_year_id' => $yearId,
            'transcript_version' => 1,
            'transcript_number' => 'TR-76-'.$suffix.'-'.uniqid(),
            'lifecycle_status' => ResultsLifecycleStatus::Finalized->value,
            'is_current' => true,
            'payload_hash' => hash('sha256', '76-http-tr'),
            'source_fingerprint' => hash('sha256', '76-http-tr-src'),
            'policy_pin' => json_encode(['test' => true]),
            'issued_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'school_id' => $schoolId,
            'year_id' => $yearId,
            'enrollment_id' => (int) $enrollment->id,
            'class_id' => (int) $enrollment->class_id,
            'term_id' => $termId,
            'subject_id' => $subjectId,
            'term_result_id' => $termResultId,
            'annual_id' => $annualId,
            'gpa_id' => $gpaId,
            'snapshot_id' => $snapshotId,
            'transcript_id' => $transcriptId,
        ];
    }
}
