<?php

namespace Tests\Unit\Optimization\SelfHealing;

use App\Optimization\SelfHealing\CheckpointService;
use App\Optimization\SelfHealing\CheckpointStatus;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CheckpointLifecycleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['optimization.checkpoints_path' => storage_path('framework/testing/checkpoint-lifecycle')]);
        File::deleteDirectory(config('optimization.checkpoints_path'));
    }

    #[Test]
    public function s12_checkpoint_lifecycle_transitions_persist_all_states(): void
    {
        $service = new CheckpointService;
        $id = $service->create([
            'operation_id' => 'OP-LIFE',
            'incident_id' => 'INC-LIFE',
            'recommendation_id' => 42,
            'target' => 'public.students',
            'action' => 'analyze',
            'before_state' => ['p95_latency_ms' => 100],
            'rollback_supported' => false,
        ]);

        $service->transition($id, CheckpointStatus::ExecutionStarted);
        $service->transition($id, CheckpointStatus::Executed);
        $service->transition($id, CheckpointStatus::AfterCaptured, ['after_state' => ['p95_latency_ms' => 85]]);
        $service->transition($id, CheckpointStatus::GuardEvaluated, ['guard_status' => 'passed']);
        $service->transition($id, CheckpointStatus::StabilizationStarted, ['stabilization_status' => 'started']);

        $checkpoint = $service->load($id);

        $this->assertSame('public.students', $checkpoint['target']);
        $this->assertSame(42, $checkpoint['recommendation_id']);
        $this->assertSame('passed', $checkpoint['guard_status']);
        $this->assertSame('started', $checkpoint['stabilization_status']);
        $this->assertNotNull($checkpoint['before_state']);
        $this->assertNotNull($checkpoint['after_state']);
    }
}
