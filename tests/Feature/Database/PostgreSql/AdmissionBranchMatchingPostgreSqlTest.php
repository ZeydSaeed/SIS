<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Domain\Admission\Repositories\AdmissionRepositoryInterface;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

/**
 * An application that names its branch only by text (no branch_id) resolves to a branch by equality — after the
 * Arabic normalization the UI uses — never by "contains", and never to a guess when the name is ambiguous.
 */
final class AdmissionBranchMatchingPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    private function branch(int $schoolId, string $code, string $name): int
    {
        return (int) DB::table('organization.branches')->insertGetId([
            'school_id' => $schoolId, 'code' => $code, 'name' => $name, 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function resolvedBranch(int $schoolId, string $branchName): ?int
    {
        $yearId = $this->createAcademicYear();
        $periodId = (int) DB::table('admission.application_periods')->insertGetId([
            'academic_year_id' => $yearId, 'name' => 'P', 'start_date' => now()->subDay(), 'end_date' => now()->addMonth(),
            'max_applications' => 100, 'status' => 1, 'created_at' => now(),
        ]);
        $id = (int) DB::table('admission.applications')->insertGetId([
            'application_period_id' => $periodId, 'school_id' => $schoolId, 'application_number' => 'BM-'.uniqid(),
            'first_name' => 'Ali', 'last_name' => 'Match', 'birth_date' => '2010-01-01', 'gender' => 1, 'status' => 2,
            'branch_id' => null, 'branch_name' => $branchName, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return app(AdmissionRepositoryInterface::class)->findApplicationForSchool($id, $schoolId)['branch_id'] ?? null;
    }

    #[Test]
    public function branch_names_match_by_equality_never_by_containment(): void
    {
        $schoolId = $this->createSchool('SCH-BM', 'Branch matching');
        $industrial = $this->branch($schoolId, 'BR-1', 'الصناعي');
        $this->branch($schoolId, 'BR-2', 'الحاسوب وتقنية المعلومات');

        // Exact, and equal after the UI's normalization (hamza / ta marbuta / the definite article).
        $this->assertSame($industrial, $this->resolvedBranch($schoolId, 'الصناعي'));
        $this->assertSame($industrial, $this->resolvedBranch($schoolId, '  الصناعي  '));
        $this->assertSame($industrial, $this->resolvedBranch($schoolId, 'صناعي'));

        // A name that merely contains / is contained in another branch's name is not that branch.
        $this->assertNull($this->resolvedBranch($schoolId, 'الصناعات الغذائية'));
        $this->assertNull($this->resolvedBranch($schoolId, 'الحاسوب'));
        $this->assertNull($this->resolvedBranch($schoolId, 'صناعي وتجاري'));
        $this->assertNull($this->resolvedBranch($schoolId, ''));
    }

    #[Test]
    public function an_ambiguous_name_resolves_to_nothing(): void
    {
        $schoolId = $this->createSchool('SCH-BM2', 'Branch matching 2');
        $this->branch($schoolId, 'BR-A', 'الإدارة');
        $this->branch($schoolId, 'BR-B', 'الادارة');

        $this->assertNull($this->resolvedBranch($schoolId, 'إدارة'));
    }
}
