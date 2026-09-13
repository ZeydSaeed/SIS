<?php

namespace Tests\Unit\Vocational;

use App\Domain\Vocational\Support\WorkshopCapacityRules;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WorkshopCapacityRulesTest extends TestCase
{
    #[Test]
    public function rejects_safety_greater_than_capacity(): void
    {
        $this->assertSame(
            ['vocational.workshop_safety_exceeds_capacity'],
            WorkshopCapacityRules::validate(10, 12),
        );
    }

    #[Test]
    public function accepts_equal_safety_and_capacity(): void
    {
        $this->assertSame([], WorkshopCapacityRules::validate(20, 20));
    }

    #[Test]
    public function detects_assignment_over_safety(): void
    {
        $this->assertTrue(WorkshopCapacityRules::wouldExceedSafety(15, 16));
        $this->assertFalse(WorkshopCapacityRules::wouldExceedSafety(15, 15));
    }
}
