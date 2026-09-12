<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Application\Results\Commands\IssueTranscriptCommand;
use App\Application\Results\Commands\IssueTranscriptHandler;
use App\Database\SchemaHelper;
use App\Domain\Results\Exceptions\TranscriptOfficialGpaMissingException;
use App\Domain\Results\ValueObjects\GpaScope;
use App\Domain\Results\ValueObjects\ResultsLifecycleStatus;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class Phase75IssueTranscriptPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function transcripts_table_exists_with_force_rls(): void
    {
        $exists = DB::selectOne("
            SELECT 1 AS ok
            FROM information_schema.tables
            WHERE table_schema = 'results' AND table_name = 'transcripts'
        ");
        $this->assertNotNull($exists);

        $rls = DB::selectOne("
            SELECT c.relrowsecurity AS rls, c.relforcerowsecurity AS force_rls
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'results' AND c.relname = 'transcripts'
        ");
        $this->assertTrue((bool) $rls->rls);
        $this->assertTrue((bool) $rls->force_rls);
    }

    #[Test]
    public function issue_transcript_requires_official_gpa(): void
    {
        $schoolId = $this->createSchool('SCH-75-U08A', 'TR A');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
        $enrollment = $this->createActiveEnrollmentForSchool($schoolId);

        $this->expectException(TranscriptOfficialGpaMissingException::class);
        $this->app->make(IssueTranscriptHandler::class)->handle(new IssueTranscriptCommand(
            schoolId: $schoolId,
            enrollmentId: (int) $enrollment->id,
            academicYearId: (int) $enrollment->academic_year_id,
            idempotencyKey: '75-u08-miss',
            issuedBy: (int) $user->id,
        ));
    }

    #[Test]
    public function issue_transcript_creates_current_issued_metadata_and_supersedes(): void
    {
        $schoolId = $this->createSchool('SCH-75-U08B', 'TR B');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
        $enrollment = $this->createActiveEnrollmentForSchool($schoolId);

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
            'source_fingerprint' => hash('sha256', 'u08-gpa'),
            'calculation_version' => 1,
            'policy_pin' => json_encode(['test' => true]),
            'calculated_at' => now(),
            'finalized_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $first = $this->app->make(IssueTranscriptHandler::class)->handle(new IssueTranscriptCommand(
            schoolId: $schoolId,
            enrollmentId: (int) $enrollment->id,
            academicYearId: (int) $enrollment->academic_year_id,
            idempotencyKey: '75-u08-issue-1',
            storageKey: null,
            issuedBy: (int) $user->id,
        ));

        $this->assertTrue($first->success);
        $this->assertSame(1, $first->transcriptVersion);
        $this->assertDatabaseHas(SchemaHelper::qualified('results', 'transcripts'), [
            'id' => $first->transcriptId,
            'is_current' => true,
            'lifecycle_status' => ResultsLifecycleStatus::Finalized->value,
            'payload_hash' => $first->payloadHash,
        ]);

        $second = $this->app->make(IssueTranscriptHandler::class)->handle(new IssueTranscriptCommand(
            schoolId: $schoolId,
            enrollmentId: (int) $enrollment->id,
            academicYearId: (int) $enrollment->academic_year_id,
            idempotencyKey: '75-u08-issue-2',
            issuedBy: (int) $user->id,
        ));

        $this->assertSame(2, $second->transcriptVersion);
        $this->assertDatabaseHas(SchemaHelper::qualified('results', 'transcripts'), [
            'id' => $first->transcriptId,
            'is_current' => false,
            'lifecycle_status' => ResultsLifecycleStatus::Superseded->value,
        ]);
        $this->assertDatabaseHas(SchemaHelper::qualified('results', 'transcripts'), [
            'id' => $second->transcriptId,
            'is_current' => true,
            'transcript_version' => 2,
        ]);
    }
}
