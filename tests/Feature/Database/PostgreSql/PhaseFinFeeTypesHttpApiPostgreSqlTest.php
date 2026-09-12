<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Security\Authorization\Permission;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseFinFeeTypesHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function fee_types_table_has_force_rls(): void
    {
        $row = DB::selectOne("
            SELECT c.relrowsecurity AS rls_enabled, c.relforcerowsecurity AS rls_forced
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'finance' AND c.relname = 'fee_types'
        ");
        $this->assertTrue((bool) $row->rls_enabled);
        $this->assertTrue((bool) $row->rls_forced);
    }

    #[Test]
    public function finance_permissions_are_registered(): void
    {
        $this->assertArrayHasKey(Permission::FINANCE_VIEW, config('security.permissions'));
        $this->assertArrayHasKey(Permission::FINANCE_MANAGE, config('security.permissions'));
    }

    #[Test]
    public function manager_can_create_and_list_fee_types(): void
    {
        $schoolId = $this->createSchool('SCH-FIN-1', 'Finance 1');
        $this->actingAsFinanceManagerForSchool($schoolId);

        $create = $this->postJson('/api/v1/finance/fee-types', [
            'code' => 'tuition',
            'name' => 'Tuition',
            'amount' => '100.50',
            'is_recurring' => true,
        ], ['X-Idempotency-Key' => 'fin-fee-1'])
            ->assertCreated();

        $feeTypeId = (int) $create->json('data.fee_type_id');

        $this->postJson('/api/v1/finance/fee-types', [
            'code' => 'tuition',
            'name' => 'Tuition',
            'amount' => '100.50',
            'is_recurring' => true,
        ], ['X-Idempotency-Key' => 'fin-fee-1'])
            ->assertOk()
            ->assertJsonPath('data.fee_type_id', $feeTypeId)
            ->assertJsonPath('data.from_idempotency', true);

        $this->getJson('/api/v1/finance/fee-types')
            ->assertOk()
            ->assertJsonPath('data.0.id', $feeTypeId)
            ->assertJsonPath('data.0.code', 'TUITION')
            ->assertJsonPath('data.0.amount', '100.50');

        $this->assertDatabaseHas(SchemaHelper::qualified('finance', 'fee_types'), [
            'id' => $feeTypeId,
            'school_id' => $schoolId,
            'code' => 'TUITION',
        ]);
    }

    #[Test]
    public function viewer_cannot_create_fee_type(): void
    {
        $schoolId = $this->createSchool('SCH-FIN-2', 'Finance 2');
        $this->actingAsFinanceViewerForSchool($schoolId);

        $this->postJson('/api/v1/finance/fee-types', [
            'code' => 'lab',
            'name' => 'Lab',
            'amount' => '10.00',
        ], ['X-Idempotency-Key' => 'fin-deny'])
            ->assertForbidden();
    }
}
