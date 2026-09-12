<?php

namespace App\Domain\Results\Services;

use App\Domain\Results\Data\AnnualResultCalculation;
use App\Domain\Results\Data\TermResultRollupRow;
use App\Domain\Results\Exceptions\AnnualResultNoTermResultsException;

final class AnnualResultCalculator
{
    /**
     * @param  list<TermResultRollupRow>  $rows
     * @param  array<string, mixed>  $policyPin
     */
    public static function calculateOperational(array $rows, array $policyPin = []): AnnualResultCalculation
    {
        return self::calculate($rows, 'operational', 'term_results.is_current_operational', $policyPin);
    }

    /**
     * @param  list<TermResultRollupRow>  $rows
     * @param  array<string, mixed>  $policyPin
     */
    public static function calculateOfficial(array $rows, array $policyPin = []): AnnualResultCalculation
    {
        return self::calculate($rows, 'official', 'term_results.is_current_official', $policyPin);
    }

    /**
     * @param  list<TermResultRollupRow>  $rows
     * @param  array<string, mixed>  $policyPin
     */
    private static function calculate(
        array $rows,
        string $mode,
        string $source,
        array $policyPin = [],
    ): AnnualResultCalculation {
        if ($rows === []) {
            throw AnnualResultNoTermResultsException::forIdentity();
        }

        $counted = count($rows);
        $passed = 0;
        $incompleteCount = 0;
        $sum = 0.0;
        $avgN = 0;
        $incomplete = false;
        $ids = [];

        foreach ($rows as $row) {
            $ids[] = $row->termResultId;
            if ($row->incomplete || $row->weightedTotal === null) {
                $incomplete = true;
                $incompleteCount++;
            }
            if ($row->passFail === 1) {
                $passed++;
            }
            if ($row->weightedTotal !== null) {
                $sum += (float) $row->weightedTotal;
                $avgN++;
            }
        }

        $average = $avgN > 0
            ? number_format(floor(($sum / $avgN) * 100 + 0.5) / 100, 2, '.', '')
            : null;

        sort($ids);

        return new AnnualResultCalculation(
            subjectsCounted: $counted,
            subjectsPassed: $passed,
            subjectsIncomplete: $incompleteCount,
            averageWeightedTotal: $average,
            incomplete: $incomplete,
            sourceFingerprint: hash('sha256', implode(',', $ids).'|annual-'.$mode),
            sourceTermResultIds: $ids,
            policyPin: array_merge([
                'mode' => $mode,
                'source' => $source,
                'source_term_result_ids' => $ids,
            ], $policyPin),
        );
    }
}
