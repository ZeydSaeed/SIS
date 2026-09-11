<?php

namespace Tests\Unit\Graduation;

use App\Domain\Graduation\Exceptions\EvaluatorApproverConflictException;
use App\Domain\Graduation\Services\EvaluatorApproverSeparation;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EvaluatorApproverSeparationTest extends TestCase
{
    #[Test]
    public function same_actor_is_rejected(): void
    {
        $this->expectException(EvaluatorApproverConflictException::class);
        EvaluatorApproverSeparation::assertDistinct(10, 10);
    }

    #[Test]
    public function null_evaluator_is_rejected(): void
    {
        $this->expectException(EvaluatorApproverConflictException::class);
        EvaluatorApproverSeparation::assertDistinct(null, 10);
    }

    #[Test]
    public function distinct_actors_pass(): void
    {
        EvaluatorApproverSeparation::assertDistinct(10, 11);
        $this->assertTrue(true);
    }
}
