<?php

namespace App\Domain\Results\Services;

use App\Domain\Exams\ValueObjects\GradeStatus;
use App\Domain\Results\Data\TermGradeContribution;
use App\Domain\Results\Data\TermResultCalculation;
use App\Domain\Results\Exceptions\TermResultNoGradesException;
use App\Domain\Results\Exceptions\TermResultWeightException;

/**
 * Operational term-subject weighted total (Design Lock HD-7.4-004…007, 014, 015).
 */
final class TermResultCalculator
{
    private const WEIGHT_EPSILON = 0.01;

    /**
     * @param  list<TermGradeContribution>  $contributions
     * @param  array<string, mixed>  $policyPin
     */
    public static function calculateOperational(array $contributions, array $policyPin = [], ?string $passThreshold = null): TermResultCalculation
    {
        return self::calculate(
            $contributions,
            allowedStatuses: [GradeStatus::Entered->value, GradeStatus::Finalized->value],
            mode: 'operational',
            policyPin: $policyPin,
            passThreshold: $passThreshold,
        );
    }

    /**
     * @param  list<TermGradeContribution>  $contributions
     * @param  array<string, mixed>  $policyPin
     */
    public static function calculateOfficial(array $contributions, array $policyPin = [], ?string $passThreshold = null): TermResultCalculation
    {
        return self::calculate(
            $contributions,
            allowedStatuses: [GradeStatus::Finalized->value],
            mode: 'official',
            policyPin: $policyPin,
            passThreshold: $passThreshold,
        );
    }

    /**
     * @param  list<TermGradeContribution>  $contributions
     * @param  list<int>  $allowedStatuses
     * @param  array<string, mixed>  $policyPin
     */
    private static function calculate(
        array $contributions,
        array $allowedStatuses,
        string $mode,
        array $policyPin = [],
        ?string $passThreshold = null,
    ): TermResultCalculation {
        $eligible = [];
        foreach ($contributions as $row) {
            if (! in_array($row->status, $allowedStatuses, true)) {
                continue;
            }
            $eligible[] = $row;
        }

        if ($eligible === []) {
            throw TermResultNoGradesException::forIdentity();
        }

        $weightsByType = [];
        foreach ($eligible as $row) {
            $weightsByType[$row->examTypeId] = $row->weightPercentage;
        }
        $weightSum = array_sum($weightsByType);
        if (abs($weightSum - 100.0) > self::WEIGHT_EPSILON) {
            throw TermResultWeightException::sumNotOneHundred(number_format($weightSum, 2, '.', ''));
        }

        $numerator = 0.0;
        $denominator = 0.0;
        $incomplete = false;
        $gradeIds = [];

        foreach ($eligible as $row) {
            $gradeIds[] = $row->gradeId;
            if ($row->isAbsent) {
                $incomplete = true;

                continue;
            }

            $max = (float) $row->maxScore;
            if ($max <= 0.0 || $row->score === null) {
                $incomplete = true;

                continue;
            }

            $weight = (float) $row->weightPercentage;
            $numerator += ((float) $row->score / $max) * 100.0 * ($weight / 100.0);
            $denominator += $weight;
        }

        $weightedTotal = null;
        $passFail = null;
        if ($denominator > 0.0) {
            $weightedTotal = self::roundHalfUp($numerator, 2);
            if ($passThreshold !== null) {
                $passFail = ((float) $weightedTotal) >= (float) $passThreshold ? 1 : 0;
            }
        } else {
            $incomplete = true;
        }

        sort($gradeIds);
        $fingerprint = hash('sha256', implode(',', $gradeIds).'|'.$weightSum.'|'.$mode);

        return new TermResultCalculation(
            weightedTotal: $weightedTotal,
            passFail: $passFail,
            incomplete: $incomplete,
            sourceFingerprint: $fingerprint,
            sourceGradeIds: $gradeIds,
            policyPin: array_merge([
                'mode' => $mode,
                'statuses' => $allowedStatuses,
                'weight_rule' => 'sum_100_fail_closed',
                'absent' => 'exclude_incomplete',
                'source_grade_ids' => $gradeIds,
            ], $policyPin),
        );
    }

    private static function roundHalfUp(float $value, int $precision): string
    {
        $factor = 10 ** $precision;
        $rounded = floor($value * $factor + 0.5) / $factor;

        return number_format($rounded, $precision, '.', '');
    }
}
