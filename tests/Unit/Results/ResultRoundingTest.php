<?php

namespace Tests\Unit\Results;

use App\Domain\Results\Support\ResultRounding;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ResultRoundingTest extends TestCase
{
    /**
     * @return iterable<string, array{list<array{score: string, max: string, weight: int}>, string}>
     */
    public static function weightedCases(): iterable
    {
        yield 'single full weight' => [[['score' => '80', 'max' => '100', 'weight' => 100]], '80.00'];
        yield 'exact .xx5 rounds up (float gave 16.68)' => [[
            ['score' => '0.04', 'max' => '100', 'weight' => 50],
            ['score' => '33.33', 'max' => '100', 'weight' => 50],
        ], '16.69'];
        yield 'pass boundary 49.995 → 50.00 (float gave 49.99)' => [[
            ['score' => '36.01', 'max' => '100', 'weight' => 50],
            ['score' => '63.98', 'max' => '100', 'weight' => 50],
        ], '50.00'];
        yield 'just below boundary stays below' => [[
            ['score' => '36.01', 'max' => '100', 'weight' => 50],
            ['score' => '63.97', 'max' => '100', 'weight' => 50],
        ], '49.99'];
        // 1/3·50 + 2/3·50 = 50 exactly — per-term truncation would give 49.99.
        yield 'repeating fractions sum exactly' => [[
            ['score' => '1', 'max' => '3', 'weight' => 50],
            ['score' => '2', 'max' => '3', 'weight' => 50],
        ], '50.00'];
        yield 'different max scores' => [[
            ['score' => '17', 'max' => '20', 'weight' => 30],
            ['score' => '41.5', 'max' => '50', 'weight' => 70],
        ], '83.60'];
        yield 'all zero' => [[['score' => '0', 'max' => '100', 'weight' => 100]], '0.00'];
        yield 'all full' => [[['score' => '100', 'max' => '100', 'weight' => 100]], '100.00'];
    }

    /**
     * @param  list<array{score: string, max: string, weight: int}>  $terms
     */
    #[Test]
    #[DataProvider('weightedCases')]
    public function weighted_percentage_rounds_half_up_exactly(array $terms, string $expected): void
    {
        $this->assertSame($expected, ResultRounding::weightedPercentage($terms));
    }

    #[Test]
    public function weighted_percentage_matches_integer_oracle_on_a_grid(): void
    {
        foreach ([[50, 50], [30, 70], [25, 75], [10, 90]] as [$w1, $w2]) {
            for ($a = 0; $a <= 10000; $a += 7) {
                foreach ([0, 3333, 5000, 6667, 9999] as $b) {
                    // Both max 100: total·10⁴ = a·w1 + b·w2 (a, b in hundredths) → half-up to hundredths.
                    $expected = number_format(intdiv($a * $w1 + $b * $w2 + 50, 100) / 100, 2, '.', '');
                    $actual = ResultRounding::weightedPercentage([
                        ['score' => number_format($a / 100, 2, '.', ''), 'max' => '100', 'weight' => $w1],
                        ['score' => number_format($b / 100, 2, '.', ''), 'max' => '100', 'weight' => $w2],
                    ]);
                    $this->assertSame($expected, $actual, "w={$w1}/{$w2} a={$a} b={$b}");
                }
            }
        }
    }

    #[Test]
    public function mean_rounds_half_up_exactly(): void
    {
        $this->assertSame('85.00', ResultRounding::mean(['80.00', '90.00']));
        // (49.00 + 49.23) / 2 = 49.115 → 49.12 (float gave 49.11)
        $this->assertSame('49.12', ResultRounding::mean(['49.00', '49.23']));
        // (50.00 + 49.99) / 2 = 49.995 → 50.00
        $this->assertSame('50.00', ResultRounding::mean(['50.00', '49.99']));
        // 100 / 3 = 33.333… → 33.33
        $this->assertSame('33.33', ResultRounding::mean(['100.00', '0.00', '0.00']));
    }
}
