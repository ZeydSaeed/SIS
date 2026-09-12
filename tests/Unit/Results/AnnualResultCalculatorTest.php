<?php

namespace Tests\Unit\Results;

use App\Domain\Results\Data\TermResultRollupRow;
use App\Domain\Results\Exceptions\AnnualResultNoTermResultsException;
use App\Domain\Results\Services\AnnualResultCalculator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AnnualResultCalculatorTest extends TestCase
{
    #[Test]
    public function averages_term_totals(): void
    {
        $result = AnnualResultCalculator::calculateOperational([
            new TermResultRollupRow(1, 1, 10, '80.00', 1, false),
            new TermResultRollupRow(2, 1, 11, '90.00', 1, false),
        ]);

        $this->assertSame(2, $result->subjectsCounted);
        $this->assertSame(2, $result->subjectsPassed);
        $this->assertSame('85.00', $result->averageWeightedTotal);
        $this->assertFalse($result->incomplete);
    }

    #[Test]
    public function fails_without_rows(): void
    {
        $this->expectException(AnnualResultNoTermResultsException::class);
        AnnualResultCalculator::calculateOperational([]);
    }
}
