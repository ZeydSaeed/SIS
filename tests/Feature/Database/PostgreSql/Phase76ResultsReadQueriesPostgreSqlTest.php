<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Application\Results\Queries\GetCurrentRankingSnapshotHandler;
use App\Application\Results\Queries\GetCurrentRankingSnapshotQuery;
use App\Application\Results\Queries\GetIssuedTranscriptMetadataHandler;
use App\Application\Results\Queries\GetIssuedTranscriptMetadataQuery;
use App\Application\Results\Queries\GetOfficialAnnualResultHandler;
use App\Application\Results\Queries\GetOfficialAnnualResultQuery;
use App\Application\Results\Queries\GetOfficialTermResultHandler;
use App\Application\Results\Queries\GetOfficialTermResultQuery;
use App\Application\Results\Queries\GetOfficialYearGpaHandler;
use App\Application\Results\Queries\GetOfficialYearGpaQuery;
use App\Database\SchemaHelper;
use App\Domain\Results\ValueObjects\GpaScope;
use App\Domain\Results\ValueObjects\ResultsLifecycleStatus;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class Phase76ResultsReadQueriesPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function official_results_gpa_ranking_transcript_queries_return_current_rows(): void
    {
        $schoolId = $this->createSchool('SCH-76-R01', 'Read 76');
        $yearId = $this->createAcademicYear();
        $enrollment = $this->createActiveEnrollmentForSchool($schoolId, $yearId);
        $termId = (int) DB::table(SchemaHelper::qualified('academic', 'terms'))->insertGetId([
            'academic_year_id' => $yearId,
            'code' => 'T1-76',
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
            'source_fingerprint' => hash('sha256', '76-term'),
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
            'source_fingerprint' => hash('sha256', '76-annual'),
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
            'source_fingerprint' => hash('sha256', '76-gpa'),
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
            'source_fingerprint' => hash('sha256', '76-rank'),
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
            'transcript_number' => 'TR-76-'.uniqid(),
            'lifecycle_status' => ResultsLifecycleStatus::Finalized->value,
            'is_current' => true,
            'payload_hash' => hash('sha256', '76-tr'),
            'source_fingerprint' => hash('sha256', '76-tr-src'),
            'policy_pin' => json_encode(['test' => true]),
            'issued_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $term = $this->app->make(GetOfficialTermResultHandler::class)->handle(new GetOfficialTermResultQuery(
            schoolId: $schoolId,
            enrollmentId: (int) $enrollment->id,
            academicYearId: $yearId,
            termId: $termId,
            subjectId: $subjectId,
        ));
        $this->assertNotNull($term);
        $this->assertSame($termResultId, $term->termResultId);
        $this->assertSame('88.00', $term->weightedTotal);

        $annual = $this->app->make(GetOfficialAnnualResultHandler::class)->handle(new GetOfficialAnnualResultQuery(
            schoolId: $schoolId,
            enrollmentId: (int) $enrollment->id,
            academicYearId: $yearId,
        ));
        $this->assertNotNull($annual);
        $this->assertSame($annualId, $annual->annualResultId);

        $gpa = $this->app->make(GetOfficialYearGpaHandler::class)->handle(new GetOfficialYearGpaQuery(
            schoolId: $schoolId,
            enrollmentId: (int) $enrollment->id,
            academicYearId: $yearId,
        ));
        $this->assertNotNull($gpa);
        $this->assertSame($gpaId, $gpa->gpaResultId);
        $this->assertSame('PERCENT_100', $gpa->scaleCode);

        $rank = $this->app->make(GetCurrentRankingSnapshotHandler::class)->handle(new GetCurrentRankingSnapshotQuery(
            schoolId: $schoolId,
            academicYearId: $yearId,
            classId: (int) $enrollment->class_id,
        ));
        $this->assertNotNull($rank);
        $this->assertSame($snapshotId, $rank->rankingSnapshotId);
        $this->assertSame(GetCurrentRankingSnapshotHandler::COMPARATIVE_LABEL, $rank->comparativeProjectionLabel);
        $this->assertCount(1, $rank->entries);

        $tr = $this->app->make(GetIssuedTranscriptMetadataHandler::class)->handle(new GetIssuedTranscriptMetadataQuery(
            schoolId: $schoolId,
            enrollmentId: (int) $enrollment->id,
            academicYearId: $yearId,
        ));
        $this->assertNotNull($tr);
        $this->assertSame($transcriptId, $tr->transcriptId);
    }

    #[Test]
    public function official_term_query_returns_null_when_missing(): void
    {
        $schoolId = $this->createSchool('SCH-76-R02', 'Read miss');
        $yearId = $this->createAcademicYear();
        $enrollment = $this->createActiveEnrollmentForSchool($schoolId, $yearId);

        $dto = $this->app->make(GetOfficialTermResultHandler::class)->handle(new GetOfficialTermResultQuery(
            schoolId: $schoolId,
            enrollmentId: (int) $enrollment->id,
            academicYearId: $yearId,
            termId: 999999,
            subjectId: 999999,
        ));
        $this->assertNull($dto);
    }
}
