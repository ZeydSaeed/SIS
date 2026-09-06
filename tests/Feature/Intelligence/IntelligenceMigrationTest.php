<?php

namespace Tests\Feature\Intelligence;

use App\Intelligence\Models\MonitoringSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntelligenceMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_intelligence_tables_exist_after_migrate(): void
    {
        $this->assertDatabaseCount((new MonitoringSnapshot)->getTable(), 0);
    }

    public function test_guardian_health_cycle_runs_on_sqlite(): void
    {
        $this->artisan('intelligence:guardian', ['cycle' => 'health'])
            ->assertSuccessful();

        $this->assertDatabaseCount((new MonitoringSnapshot)->getTable(), 1);
    }
}
