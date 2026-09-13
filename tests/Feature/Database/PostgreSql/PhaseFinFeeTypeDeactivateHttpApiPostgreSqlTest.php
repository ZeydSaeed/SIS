<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseFinFeeTypeDeactivateHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_deactivate_fee_type(): void
    {
        $schoolId = $this->createSchool('SCH-FIN-D1', 'Fee Type Deactivate');
        $this->actingAsFinanceManagerForSchool($schoolId);

        $feeTypeId = (int) $this->postJson('/api/v1/finance/fee-types', [
            'code' => 'tuition',
            'name' => 'Tuition',
            'amount' => '100.00',
        ], ['X-Idempotency-Key' => 'fin-ft-d-create'])->json('data.fee_type_id');

        $this->postJson('/api/v1/finance/fee-types/'.$feeTypeId.'/deactivate', [], [
            'X-Idempotency-Key' => 'fin-ft-d-off',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 2);

        $this->assertDatabaseHas(SchemaHelper::qualified('finance', 'fee_types'), [
            'id' => $feeTypeId,
            'status' => 2,
        ]);
    }
}
