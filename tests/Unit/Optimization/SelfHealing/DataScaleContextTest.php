<?php

namespace Tests\Unit\Optimization\SelfHealing;

use App\Optimization\SelfHealing\DataScaleContext;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DataScaleContextTest extends TestCase
{
    #[Test]
    public function s15_different_data_scales_produce_different_tiers(): void
    {
        $context = new DataScaleContext;

        $this->assertSame('small', $context->tier(512));
        $this->assertSame('medium', $context->tier(5000));
        $this->assertSame('large', $context->tier(50000));
        $this->assertSame('unknown', $context->tier(null));
    }

    #[Test]
    public function s15_unknown_scale_blocks_when_configured(): void
    {
        config(['optimization.data_scale.block_autonomous_on_unknown' => true]);
        $context = new DataScaleContext;

        $this->assertFalse($context->allowsAutonomousOptimization(['scale_tier' => 'unknown']));
        $this->assertTrue($context->allowsAutonomousOptimization(['scale_tier' => 'small']));
    }
}
