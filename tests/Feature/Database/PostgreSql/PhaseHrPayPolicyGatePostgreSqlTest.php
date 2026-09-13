<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Models\User;
use App\Security\Context\SchoolContext;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseHrPayPolicyGatePostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function payroll_manager_passes_payroll_gates_not_hr_manage(): void
    {
        $schoolId = $this->createSchool('SCH-PAY-G1', 'Payroll Gate');
        $user = $this->actingAsHrPayrollManagerForSchool($schoolId);
        $this->app->make(SchoolContext::class)->set($schoolId);

        $this->assertTrue($user->can('viewHrPayroll'));
        $this->assertTrue($user->can('manageHrPayroll'));
        $this->assertFalse($user->can('manageHr'));
    }

    #[Test]
    public function hr_manager_does_not_pass_payroll_gates(): void
    {
        $schoolId = $this->createSchool('SCH-PAY-G2', 'HR Gate');
        $user = $this->actingAsHrManagerForSchool($schoolId);
        $this->app->make(SchoolContext::class)->set($schoolId);

        $this->assertTrue($user->can('manageHr'));
        $this->assertFalse($user->can('viewHrPayroll'));
        $this->assertFalse($user->can('manageHrPayroll'));
    }

    #[Test]
    public function payroll_viewer_can_view_but_not_manage_payroll(): void
    {
        $schoolId = $this->createSchool('SCH-PAY-G3', 'Payroll Viewer Gate');
        /** @var User $user */
        $user = $this->actingAsHrPayrollViewerForSchool($schoolId);
        $this->app->make(SchoolContext::class)->set($schoolId);

        $this->assertTrue($user->can('viewHrPayroll'));
        $this->assertFalse($user->can('manageHrPayroll'));
    }
}
