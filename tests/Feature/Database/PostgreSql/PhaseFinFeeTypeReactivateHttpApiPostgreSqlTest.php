<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseFinFeeTypeReactivateHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_reactivate_fee_type(): void
    {
        $schoolId = $this->createSchool('SCH-FIN-R1', 'Fee Type Reactivate');
        $this->actingAsFinanceManagerForSchool($schoolId);

        $feeTypeId = (int) $this->postJson('/api/v1/finance/fee-types', [
            'code' => 'lab',
            'name' => 'Lab',
            'amount' => '50.00',
        ], ['X-Idempotency-Key' => 'fin-ft-r-create'])->json('data.fee_type_id');

        $this->postJson('/api/v1/finance/fee-types/'.$feeTypeId.'/deactivate', [], [
            'X-Idempotency-Key' => 'fin-ft-r-off',
        ])->assertOk();

        $this->postJson('/api/v1/finance/fee-types/'.$feeTypeId.'/reactivate', [], [
            'X-Idempotency-Key' => 'fin-ft-r-on',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 1);

        $this->assertDatabaseHas(SchemaHelper::qualified('finance', 'fee_types'), [
            'id' => $feeTypeId,
            'status' => 1,
        ]);
    }
}
