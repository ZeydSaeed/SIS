<?php

namespace Tests\Unit\Domain\Exams;

use App\Domain\Exams\ValueObjects\GradeStatus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GradeStatusTest extends TestCase
{
    #[Test]
    public function finalized_and_voided_disallow_score_mutation(): void
    {
        $this->assertTrue(GradeStatus::Draft->allowsScoreMutation());
        $this->assertFalse(GradeStatus::Finalized->allowsScoreMutation());
        $this->assertFalse(GradeStatus::Voided->allowsScoreMutation());
        $this->assertTrue(GradeStatus::Voided->isTerminal());
    }
}
