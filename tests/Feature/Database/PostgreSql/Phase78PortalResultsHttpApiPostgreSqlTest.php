<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Results\ValueObjects\GpaScope;
use App\Domain\Results\ValueObjects\ResultsLifecycleStatus;
use App\Security\Authorization\Permission;
use App\Security\Authorization\PortalScopeType;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Results\PublishesOfficialResults;

final class Phase78PortalResultsHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;
    use PublishesOfficialResults;

    #[Test]
    public function portal_results_view_permission_is_registered(): void
    {
        $this->assertArrayHasKey(Permission::PORTAL_RESULTS_VIEW, config('security.permissions'));
        $this->assertContains(Permission::PORTAL_RESULTS_VIEW, Permission::all());
        $this->assertContains(Permission::PORTAL_RESULTS_VIEW, config('security.roles.portal_results_viewer'));
    }

    #[Test]
    public function student_scoped_user_can_read_own_official_portal_surfaces(): void
    {
        $seed = $this->seedOfficialResults('PORTAL');
        $user = $this->actingAsPortalResultsViewerForSchool($seed['school_id']);
        $this->linkStudentScope((int) $user->id, $seed['student_id']);

        $this->getJson(sprintf(
            '/api/v1/portal/results/term?enrollment_id=%d&academic_year_id=%d&term_id=%d&subject_id=%d',
            $seed['enrollment_id'],
            $seed['year_id'],
            $seed['term_id'],
            $seed['subject_id'],
        ))->assertOk()
            ->assertJsonPath('data.term_result_id', $seed['term_result_id']);

        $this->getJson(sprintf(
            '/api/v1/portal/results/annual?enrollment_id=%d&academic_year_id=%d',
            $seed['enrollment_id'],
            $seed['year_id'],
        ))->assertOk()
            ->assertJsonPath('data.annual_result_id', $seed['annual_id']);

        $this->getJson(sprintf(
            '/api/v1/portal/results/gpa?enrollment_id=%d&academic_year_id=%d',
            $seed['enrollment_id'],
            $seed['year_id'],
        ))->assertOk()
            ->assertJsonPath('data.gpa_result_id', $seed['gpa_id']);

        $this->getJson(sprintf(
            '/api/v1/portal/results/transcripts/issued?enrollment_id=%d&academic_year_id=%d',
            $seed['enrollment_id'],
            $seed['year_id'],
        ))->assertOk()
            ->assertJsonPath('data.transcript_id', $seed['transcript_id']);
    }

    #[Test]
    public function withdrawn_publication_hides_every_official_result(): void
    {
        $seed = $this->seedOfficialResults('WDRAWN');
        $user = $this->actingAsPortalResultsViewerForSchool($seed['school_id']);
        $this->linkStudentScope((int) $user->id, $seed['student_id']);
        DB::table(SchemaHelper::qualified('results', 'result_publications'))->where('school_id', $seed['school_id'])
            ->update(['status' => 'withdrawn', 'withdrawn_at' => now(), 'withdraw_reason' => 'test']);
        $query = sprintf('enrollment_id=%d&academic_year_id=%d', $seed['enrollment_id'], $seed['year_id']);

        $this->getJson('/api/v1/portal/results/annual?'.$query)->assertNotFound()->assertJsonPath('error_code', 'results.annual_not_found');
        $this->getJson('/api/v1/portal/results/gpa?'.$query)->assertNotFound()->assertJsonPath('error_code', 'results.gpa_not_found');
        $this->getJson('/api/v1/portal/results/transcripts/issued?'.$query)->assertNotFound()->assertJsonPath('error_code', 'results.transcript_not_found');
        $this->getJson(sprintf('/api/v1/portal/results/term?%s&term_id=%d&subject_id=%d', $query, $seed['term_id'], $seed['subject_id']))->assertNotFound();
    }

    #[Test]
    public function a_newer_official_version_is_hidden_until_it_is_published_again(): void
    {
        $seed = $this->seedOfficialResults('NEWVER');
        $user = $this->actingAsPortalResultsViewerForSchool($seed['school_id']);
        $this->linkStudentScope((int) $user->id, $seed['student_id']);
        $annual = SchemaHelper::qualified('results', 'annual_results');
        // A rebuild: the published version 1 is superseded by an official version 2.
        DB::table($annual)->where('id', $seed['annual_id'])->update(['result_version' => 2]);
        $url = sprintf('/api/v1/portal/results/annual?enrollment_id=%d&academic_year_id=%d', $seed['enrollment_id'], $seed['year_id']);

        $this->getJson($url)->assertNotFound();
        DB::table(SchemaHelper::qualified('results', 'result_publications'))->where('school_id', $seed['school_id'])
            ->update(['status' => 'withdrawn', 'withdrawn_at' => now(), 'withdraw_reason' => 'republish']);
        $this->publishOfficialResult($seed['school_id'], $seed['year_id'], $seed['enrollment_id'], 'annual', 2);
        $this->getJson($url)->assertOk()->assertJsonPath('data.result_version', 2);
    }

    #[Test]
    public function web_page_lists_only_published_results_of_the_users_own_students(): void
    {
        $mine = $this->seedOfficialResults('WEB-A');
        $other = $this->seedOfficialResults('WEB-B');
        $user = $this->actingAsPortalResultsViewerForSchool($mine['school_id']);
        $this->linkStudentScope((int) $user->id, $mine['student_id']);

        $this->get('/portal/results')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('portal/results')
            ->has('rows', 1)
            ->where('rows.0.enrollment_id', $mine['enrollment_id'])
            ->where('rows.0.annual.version', 1)
            ->where('rows.0.gpa.version', 1)
            ->has('rows.0.terms', 1)
            ->where('rows.0.transcript.version', 1));
        $this->assertNotSame($mine['enrollment_id'], $other['enrollment_id']);

        DB::table(SchemaHelper::qualified('results', 'result_publications'))->where('school_id', $mine['school_id'])
            ->update(['status' => 'withdrawn', 'withdrawn_at' => now(), 'withdraw_reason' => 'test']);
        $this->get('/portal/results')->assertOk()->assertInertia(fn (Assert $page) => $page->has('rows', 0));
    }

    #[Test]
    public function web_page_needs_the_portal_permission_and_a_scope(): void
    {
        $seed = $this->seedOfficialResults('WEB-C');

        $this->actingAsPortalResultsViewerForSchool($seed['school_id']);
        $this->get('/portal/results')->assertOk()->assertInertia(fn (Assert $page) => $page->has('rows', 0));

        $this->actingAsResultsViewerForSchool($seed['school_id']);
        $this->get('/portal/results')->assertForbidden();
    }

    #[Test]
    public function guardian_scoped_user_can_read_linked_student_official_annual(): void
    {
        $seed = $this->seedOfficialResults('GUARD');
        $guardianId = $this->createGuardianLinkedToStudent($seed['student_id']);
        $user = $this->actingAsPortalResultsViewerForSchool($seed['school_id']);
        $this->linkGuardianScope((int) $user->id, $guardianId);

        $this->getJson(sprintf(
            '/api/v1/portal/results/annual?enrollment_id=%d&academic_year_id=%d',
            $seed['enrollment_id'],
            $seed['year_id'],
        ))->assertOk()
            ->assertJsonPath('data.annual_result_id', $seed['annual_id']);
    }

    #[Test]
    public function portal_permission_without_party_scope_is_forbidden(): void
    {
        $seed = $this->seedOfficialResults('NOSCOPE');
        $this->actingAsPortalResultsViewerForSchool($seed['school_id']);

        $this->getJson(sprintf(
            '/api/v1/portal/results/annual?enrollment_id=%d&academic_year_id=%d',
            $seed['enrollment_id'],
            $seed['year_id'],
        ))->assertForbidden()
            ->assertJsonPath('error_code', 'portal.results.ownership_denied');
    }

    #[Test]
    public function staff_results_viewer_cannot_use_portal_routes(): void
    {
        $seed = $this->seedOfficialResults('STAFF');
        $this->actingAsResultsViewerForSchool($seed['school_id']);

        $this->getJson(sprintf(
            '/api/v1/portal/results/annual?enrollment_id=%d&academic_year_id=%d',
            $seed['enrollment_id'],
            $seed['year_id'],
        ))->assertForbidden();
    }

    private function linkStudentScope(int $userId, int $studentId): void
    {
        DB::table(SchemaHelper::qualified('security', 'scopes'))->insert([
            'user_id' => $userId,
            'scope_type' => PortalScopeType::STUDENT,
            'scope_id' => $studentId,
            'created_at' => now(),
        ]);
    }

    private function linkGuardianScope(int $userId, int $guardianId): void
    {
        DB::table(SchemaHelper::qualified('security', 'scopes'))->insert([
            'user_id' => $userId,
            'scope_type' => PortalScopeType::GUARDIAN,
            'scope_id' => $guardianId,
            'created_at' => now(),
        ]);
    }

    private function createGuardianLinkedToStudent(int $studentId): int
    {
        $guardianId = (int) DB::table(SchemaHelper::qualified('guardians', 'guardians'))->insertGetId([
            'first_name' => 'Parent',
            'last_name' => 'One',
            'full_name' => 'Parent One',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table(SchemaHelper::qualified('guardians', 'student_guardians'))->insert([
            'student_id' => $studentId,
            'guardian_id' => $guardianId,
            'relationship_type' => 1,
            'is_primary' => true,
            'is_emergency_contact' => false,
            'created_at' => now(),
        ]);

        return $guardianId;
    }

    /**
     * @return array{
     *     school_id:int,
     *     year_id:int,
     *     enrollment_id:int,
     *     student_id:int,
     *     term_id:int,
     *     subject_id:int,
     *     term_result_id:int,
     *     annual_id:int,
     *     gpa_id:int,
     *     transcript_id:int
     * }
     */
    private function seedOfficialResults(string $suffix): array
    {
        $schoolId = $this->createSchool('SCH-78-'.$suffix, 'Portal '.$suffix);
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
            'code' => 'P'.substr(uniqid(), -8),
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
            'source_fingerprint' => hash('sha256', '78-portal-term-'.$suffix),
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
            'source_fingerprint' => hash('sha256', '78-portal-annual-'.$suffix),
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
            'source_fingerprint' => hash('sha256', '78-portal-gpa-'.$suffix),
            'calculation_version' => 1,
            'policy_pin' => json_encode(['test' => true]),
            'calculated_at' => now(),
            'finalized_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $transcriptId = (int) DB::table(SchemaHelper::qualified('results', 'transcripts'))->insertGetId([
            'school_id' => $schoolId,
            'student_id' => $enrollment->student_id,
            'enrollment_id' => $enrollment->id,
            'academic_year_id' => $yearId,
            'transcript_version' => 1,
            'transcript_number' => 'TR-78-'.$suffix.'-'.uniqid(),
            'lifecycle_status' => ResultsLifecycleStatus::Finalized->value,
            'is_current' => true,
            'payload_hash' => hash('sha256', '78-portal-tr-'.$suffix),
            'source_fingerprint' => hash('sha256', '78-portal-tr-src-'.$suffix),
            'policy_pin' => json_encode(['test' => true]),
            'issued_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->publishOfficialResult($schoolId, $yearId, (int) $enrollment->id, 'annual');
        $this->publishOfficialResult($schoolId, $yearId, (int) $enrollment->id, 'gpa');
        $this->publishOfficialResult($schoolId, $yearId, (int) $enrollment->id, 'transcript');
        $this->publishOfficialResult($schoolId, $yearId, (int) $enrollment->id, 'term', 1, $termId, $subjectId);

        return [
            'school_id' => $schoolId,
            'year_id' => $yearId,
            'enrollment_id' => (int) $enrollment->id,
            'student_id' => (int) $enrollment->student_id,
            'term_id' => $termId,
            'subject_id' => $subjectId,
            'term_result_id' => $termResultId,
            'annual_id' => $annualId,
            'gpa_id' => $gpaId,
            'transcript_id' => $transcriptId,
        ];
    }
}
