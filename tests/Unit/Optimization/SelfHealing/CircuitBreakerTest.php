<?php

namespace Tests\Unit\Optimization\SelfHealing;

use App\Optimization\SelfHealing\CircuitBreaker;
use App\Optimization\SelfHealing\SelfHealingEventLogger;
use App\Optimization\SelfHealing\SelfHealingStateStore;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CircuitBreakerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['optimization.state_path' => storage_path('framework/testing/optimization-state-cb')]);
        File::deleteDirectory(config('optimization.state_path'));
        config(['optimization.circuit_breaker.max_failed_attempts' => 3]);
    }

    #[Test]
    public function it_enters_safe_mode_after_max_failures(): void
    {
        $breaker = new CircuitBreaker(new SelfHealingStateStore, new SelfHealingEventLogger);

        $breaker->recordFailure('a');
        $breaker->recordFailure('b');
        $this->assertFalse($breaker->isOpen());

        $breaker->recordFailure('c');
        $this->assertTrue($breaker->isOpen());
    }

    #[Test]
    public function it_resets_on_manual_clear(): void
    {
        $breaker = new CircuitBreaker(new SelfHealingStateStore, new SelfHealingEventLogger);

        $breaker->recordFailure('x');
        $breaker->recordFailure('x');
        $breaker->recordFailure('x');
        $this->assertTrue($breaker->isOpen());

        $breaker->reset();
        $this->assertFalse($breaker->isOpen());
    }
}
