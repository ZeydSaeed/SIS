<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Results\ValueObjects\ResultsLifecycleStatus;
use App\Infrastructure\Persistence\Eloquent\SecurityAuditLogRecord;
use App\Security\Audit\SecurityEventType;
use App\Security\Authorization\PortalScopeType;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class Phase78PortalDenyMatrixPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function guardian_scope_without_student_guardians_is_forbidden(): void
    {
        $seed = $this->seedAnnual('G-NOLINK');
        $guardianId = $this->createGuardianOnly();
        $user = $this->actingAsPortalResultsViewerForSchool($seed['school_id']);
        $this->linkGuardianScope((int) $user->id, $guardianId);

        $this->getJson($this->annualUrl($seed))
            ->assertForbidden()
            ->assertJsonPath('error_code', 'portal.results.ownership_denied');
    }

    #[Test]
    public function dangling_guardian_scope_id_is_forbidden(): void
    {
        $seed = $this->seedAnnual('G-DANGLE');
        $user = $this->actingAsPortalResultsViewerForSchool($seed['school_id']);
        $this->linkGuardianScope((int) $user->id, 9_999_999);

        $this->getJson($this->annualUrl($seed))
            ->assertForbidden()
            ->assertJsonPath('error_code', 'portal.results.ownership_denied');
    }

    #[Test]
    public function student_scope_cannot_read_other_student(): void
    {
        $seedA = $this->seedAnnual('STU-A');
        $seedB = $this->seedAnnual('STU-B', $seedA['school_id'], $seedA['year_id']);
        $user = $this->actingAsPortalResultsViewerForSchool($seedA['school_id']);
        $this->linkStudentScope((int) $user->id, $seedA['student_id']);

        $this->getJson($this->annualUrl($seedB))
            ->assertForbidden()
            ->assertJsonPath('error_code', 'portal.results.ownership_denied');
    }

    #[Test]
    public function guardian_of_a_cannot_read_student_b(): void
    {
        $seedA = $this->seedAnnual('GA');
        $seedB = $this->seedAnnual('GB', $seedA['school_id'], $seedA['year_id']);
        $guardianId = $this->createGuardianLinkedToStudent($seedA['student_id']);
        $user = $this->actingAsPortalResultsViewerForSchool($seedA['school_id']);
        $this->linkGuardianScope((int) $user->id, $guardianId);

        $this->getJson($this->annualUrl($seedB))
            ->assertForbidden()
            ->assertJsonPath('error_code', 'portal.results.ownership_denied');

        $this->getJson($this->annualUrl($seedA))->assertOk();
    }

    #[Test]
    public function cross_school_enrollment_is_forbidden(): void
    {
        $seedA = $this->seedAnnual('SCH-A');
        $seedB = $this->seedAnnual('SCH-B');
        $user = $this->actingAsPortalResultsViewerForSchool($seedA['school_id']);
        $this->linkStudentScope((int) $user->id, $seedB['student_id']);

        // Context school A + enrollment belonging to school B → ownership/school miss.
        $this->getJson($this->annualUrl($seedB))
            ->assertForbidden()
            ->assertJsonPath('error_code', 'portal.results.ownership_denied');
    }

    #[Test]
    public function ownership_deny_writes_idor_audit(): void
    {
        $seed = $this->seedAnnual('AUDIT');
        $user = $this->actingAsPortalResultsViewerForSchool($seed['school_id']);
        $this->linkStudentScope((int) $user->id, $seed['student_id'] + 1_000_000);

        $this->getJson($this->annualUrl($seed))
            ->assertForbidden()
            ->assertJsonPath('error_code', 'portal.results.ownership_denied');

        $this->assertDatabaseHas((new SecurityAuditLogRecord)->getTable(), [
            'event_id' => SecurityEventType::IdorBlocked->value,
            'action' => 'portal.results.annual.show',
            'result' => 'denied',
            'actor_id' => $user->id,
            'school_id' => $seed['school_id'],
            'target_type' => 'enrollment',
            'target_id' => (string) $seed['enrollment_id'],
        ]);
    }

    /**
     * @param  array{enrollment_id:int, year_id:int}  $seed
     */
    private function annualUrl(array $seed): string
    {
        return sprintf(
            '/api/v1/portal/results/annual?enrollment_id=%d&academic_year_id=%d',
            $seed['enrollment_id'],
            $seed['year_id'],
        );
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

    private function createGuardianOnly(): int
    {
        return (int) DB::table(SchemaHelper::qualified('guardians', 'guardians'))->insertGetId([
            'first_name' => 'Orphan',
            'last_name' => 'Guardian',
            'full_name' => 'Orphan Guardian',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createGuardianLinkedToStudent(int $studentId): int
    {
        $guardianId = $this->createGuardianOnly();

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
     *     annual_id:int
     * }
     */
    private function seedAnnual(string $suffix, ?int $schoolId = null, ?int $yearId = null): array
    {
        $schoolId ??= $this->createSchool('SCH-78D-'.$suffix, 'Deny '.$suffix);
        $yearId ??= $this->createAcademicYear('AY-78D-'.$suffix);
        $enrollment = $this->createActiveEnrollmentForSchool($schoolId, $yearId);

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);

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
            'average_weighted_total' => '90.00',
            'incomplete' => false,
            'source_fingerprint' => hash('sha256', '78-deny-'.$suffix),
            'calculation_version' => 1,
            'policy_pin' => json_encode(['test' => true]),
            'calculated_at' => now(),
            'finalized_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'school_id' => $schoolId,
            'year_id' => $yearId,
            'enrollment_id' => (int) $enrollment->id,
            'student_id' => (int) $enrollment->student_id,
            'annual_id' => $annualId,
        ];
    }
}
