<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Security\Authorization\Permission;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseHrPayPermissionsPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function hr_payroll_permissions_and_roles_are_registered(): void
    {
        $this->assertArrayHasKey(Permission::HR_PAYROLL_VIEW, config('security.permissions'));
        $this->assertArrayHasKey(Permission::HR_PAYROLL_MANAGE, config('security.permissions'));
        $this->assertContains(Permission::HR_PAYROLL_VIEW, Permission::all());
        $this->assertContains(Permission::HR_PAYROLL_MANAGE, Permission::all());

        $this->assertContains(Permission::HR_PAYROLL_VIEW, config('security.roles.hr_payroll_viewer'));
        $this->assertContains(Permission::HR_PAYROLL_VIEW, config('security.roles.hr_payroll_manager'));
        $this->assertContains(Permission::HR_PAYROLL_MANAGE, config('security.roles.hr_payroll_manager'));
        $this->assertNotContains(Permission::HR_PAYROLL_MANAGE, config('security.roles.hr_manager'));
    }
}
