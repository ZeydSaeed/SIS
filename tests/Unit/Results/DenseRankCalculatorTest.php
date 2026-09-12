<?php

namespace Tests\Unit\Results;

use App\Domain\Results\Data\RankingParticipant;
use App\Domain\Results\Exceptions\RankingNoParticipantsException;
use App\Domain\Results\Services\DenseRankCalculator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DenseRankCalculatorTest extends TestCase
{
    #[Test]
    public function assigns_competition_skip_ranks_for_ties(): void
    {
        $ranked = DenseRankCalculator::rankByMetricDesc([
            new RankingParticipant(10, 1, 100, '100.00'),
            new RankingParticipant(20, 2, 200, '90.00'),
            new RankingParticipant(30, 3, 300, '90.00'),
            new RankingParticipant(40, 4, 400, '80.00'),
        ]);

        $this->assertSame([1, 2, 2, 4], array_map(fn ($r) => $r->rankPosition, $ranked));
        $this->assertSame([10, 20, 30, 40], array_map(fn ($r) => $r->enrollmentId, $ranked));
    }

    #[Test]
    public function rejects_empty_cohort(): void
    {
        $this->expectException(RankingNoParticipantsException::class);
        DenseRankCalculator::rankByMetricDesc([]);
    }
}
