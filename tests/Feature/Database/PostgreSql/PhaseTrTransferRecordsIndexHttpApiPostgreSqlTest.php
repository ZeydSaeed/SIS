<?php

namespace Tests\Feature\Database\PostgreSql;

use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseTrTransferRecordsIndexHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_list_transfer_records_empty(): void
    {
        $schoolId = $this->createSchool('SCH-TR-U09', 'Transfer Records Index');
        $this->actingAsTransfersManagerForSchool($schoolId);

        $this->getJson('/api/v1/transfers/records')
            ->assertOk()
            ->assertJsonPath('data', []);
    }
}
