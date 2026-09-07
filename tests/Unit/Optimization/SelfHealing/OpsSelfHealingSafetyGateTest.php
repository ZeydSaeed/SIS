<?php

namespace Tests\Unit\Optimization\SelfHealing;

use App\Intelligence\Models\MonitoringSnapshot;
use App\Intelligence\SelfHealing\OpsSelfHealingSafetyGate;
use App\Intelligence\SelfHealing\SelfHealingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OpsSelfHealingSafetyGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'intelligence.ops_self_healing.enabled' => true,
            'intelligence.ops_self_healing.allowlist' => ['replica_lag', 'connection_saturation'],
        ]);
        Cache::flush();
        app(OpsSelfHealingSafetyGate::class)->resetCircuit();
    }

    #[Test]
    public function s17_ops_self_healing_rejects_non_allowlisted_playbook(): void
    {
        $gate = app(OpsSelfHealingSafetyGate::class);

        $result = $gate->authorize('drop_index', []);

        $this->assertFalse($result['allowed']);
        $this->assertStringContainsString('allowlist', $result['reason'] ?? '');
    }

    #[Test]
    public function s17_ops_self_healing_executes_allowlisted_playbook_with_audit(): void
    {
        $snapshot = MonitoringSnapshot::query()->create([
            'snapshot_type' => 'health',
            'captured_at' => now(),
            'replication_lag_seconds' => 60,
            'connection_count' => 10,
        ]);

        Cache::put('intelligence.read_from_replica', true, 60);

        $engine = app(SelfHealingEngine::class);
        $action = $engine->evaluate($snapshot);

        $this->assertNotNull($action);
        $this->assertSame('replica_lag', $action->playbook_id);
        $this->assertFalse(Cache::get('intelligence.read_from_replica'));
    }
}
