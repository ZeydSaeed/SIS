<?php

namespace Tests\Unit\Optimization\SelfHealing;

use App\Optimization\SelfHealing\AdaptiveBaselineEngine;
use App\Optimization\SelfHealing\BaselineContextMatcher;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BaselineContextMatcherTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['optimization.adaptive_baseline_path' => storage_path('framework/testing/adaptive-baseline-context.json')]);
        File::delete(config('optimization.adaptive_baseline_path'));
    }

    #[Test]
    public function s14_compatible_context_uses_baseline(): void
    {
        $engine = new AdaptiveBaselineEngine(new BaselineContextMatcher);
        $engine->update([
            'p95_latency_ms' => 100,
            'context_fingerprint' => ['environment' => 'testing', 'cpu_cores' => 4],
        ]);

        $loaded = $engine->loadCompatible([
            'context_fingerprint' => ['environment' => 'testing', 'cpu_cores' => 4],
        ]);

        $this->assertNotEmpty($loaded['metrics']);
    }

    #[Test]
    public function s14_incompatible_context_marks_stale(): void
    {
        $engine = new AdaptiveBaselineEngine(new BaselineContextMatcher);
        $engine->update([
            'p95_latency_ms' => 100,
            'context_fingerprint' => ['environment' => 'testing', 'cpu_cores' => 4],
        ]);

        $loaded = $engine->loadCompatible([
            'context_fingerprint' => ['environment' => 'production', 'cpu_cores' => 4],
        ]);

        $this->assertEmpty($loaded['metrics']);
        $this->assertTrue($engine->isStale());
    }
}
