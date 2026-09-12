<?php

namespace App\Domain\Results\Services;

use App\Domain\Results\Data\RankedParticipant;
use App\Domain\Results\Data\RankingParticipant;
use App\Domain\Results\Exceptions\RankingNoParticipantsException;

final class DenseRankCalculator
{
    /**
     * @param  list<RankingParticipant>  $participants
     * @return list<RankedParticipant>
     */
    public static function rankByMetricDesc(array $participants): array
    {
        if ($participants === []) {
            throw RankingNoParticipantsException::forCohort();
        }

        usort($participants, static function (RankingParticipant $a, RankingParticipant $b): int {
            $av = $a->metricValue === null ? -INF : (float) $a->metricValue;
            $bv = $b->metricValue === null ? -INF : (float) $b->metricValue;
            if ($av === $bv) {
                return $a->enrollmentId <=> $b->enrollmentId;
            }

            return $bv <=> $av;
        });

        $out = [];
        $position = 0;
        $index = 0;
        $prevValue = null;

        foreach ($participants as $p) {
            $index++;
            $value = $p->metricValue;
            if ($prevValue === null || $value !== $prevValue) {
                $position = $index;
                $prevValue = $value;
            }
            $out[] = new RankedParticipant(
                enrollmentId: $p->enrollmentId,
                studentId: $p->studentId,
                gpaResultId: $p->gpaResultId,
                metricValue: $p->metricValue,
                rankPosition: $position,
            );
        }

        return $out;
    }
}
