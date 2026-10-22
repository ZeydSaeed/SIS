<?php

namespace App\Domain\Results\Support;

/**
 * Exact round-half-up to 2 decimals for result totals.
 *
 * Scores, max scores and totals are decimals, so the arithmetic runs on integers (bcmath, as Finance does).
 * Float `floor(x * 100 + 0.5)` misrounds exact halves such as 49.995 to 49.99 and flips pass/fail at the threshold.
 */
final class ResultRounding
{
    /** Inputs are NUMERIC(5,2); scaling by 10^6 keeps every input exact. */
    private const SCALE_FACTOR = '1000000';

    /**
     * Σ (score / max) × weight — the percentage total of the given components — rounded half-up to 2 decimals.
     *
     * @param  list<array{score: string, max: string, weight: int}>  $terms  non-negative scores, max > 0
     */
    public static function weightedPercentage(array $terms): string
    {
        // Running exact fraction n/d: n/d + (s·w)/m = (n·m + s·w·d) / (d·m). Scaling cancels in s/m.
        $numerator = '0';
        $denominator = '1';
        foreach ($terms as $term) {
            $score = self::scaled($term['score']);
            $max = self::scaled($term['max']);
            $numerator = bcadd(
                bcmul($numerator, $max, 0),
                bcmul(bcmul($score, (string) $term['weight'], 0), $denominator, 0),
                0,
            );
            $denominator = bcmul($denominator, $max, 0);
        }

        return self::halfUp($numerator, $denominator);
    }

    /**
     * Arithmetic mean of non-negative decimals, rounded half-up to 2 decimals.
     *
     * @param  non-empty-list<string>  $values
     */
    public static function mean(array $values): string
    {
        $sum = '0';
        foreach ($values as $value) {
            $sum = bcadd($sum, self::scaled($value), 0);
        }

        return self::halfUp($sum, bcmul((string) count($values), self::SCALE_FACTOR, 0));
    }

    /** n/d rounded half-up to 2 decimals: floor((200·n + d) / (2·d)) hundredths. */
    private static function halfUp(string $numerator, string $denominator): string
    {
        $hundredths = bcdiv(
            bcadd(bcmul($numerator, '200', 0), $denominator, 0),
            bcmul($denominator, '2', 0),
            0,
        );

        return bcdiv($hundredths, '100', 2);
    }

    private static function scaled(string $decimal): string
    {
        return bcmul($decimal, self::SCALE_FACTOR, 0);
    }
}
