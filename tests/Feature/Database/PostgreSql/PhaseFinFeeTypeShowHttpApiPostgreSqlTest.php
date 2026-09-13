<?php

namespace Tests\Feature\Database\PostgreSql;

use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseFinFeeTypeShowHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_show_fee_type(): void
    {
        $schoolId = $this->createSchool('SCH-FIN-S1', 'Finance Show FeeType');
        $this->actingAsFinanceManagerForSchool($schoolId);

        $feeTypeId = (int) $this->postJson('/api/v1/finance/fee-types', [
            'code' => 'show-ft',
            'name' => 'Show Fee Type',
            'amount' => '75.25',
            'is_recurring' => false,
        ], ['X-Idempotency-Key' => 'fin-show-ft'])->json('data.fee_type_id');

        $this->getJson('/api/v1/finance/fee-types/'.$feeTypeId)
            ->assertOk()
            ->assertJsonPath('data.id', $feeTypeId)
            ->assertJsonPath('data.code', 'SHOW-FT')
            ->assertJsonPath('data.name', 'Show Fee Type')
            ->assertJsonPath('data.amount', '75.25');
    }
}
