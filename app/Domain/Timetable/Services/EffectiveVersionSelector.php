<?php

namespace App\Domain\Timetable\Services;

use App\Domain\Timetable\ValueObjects\TimetableVersionStatus;

/**
 * Which published timetable governs a date (spec §115–119): the version published (or since superseded —
 * history stays valid for its own dates) whose effective range contains the date; the latest published wins
 * an overlap. None → the working grid governs (no version published yet).
 */
final class EffectiveVersionSelector
{
    /**
     * @param  list<array{id: int, status: int, effective_from: string|null, effective_to: string|null, published_at: string|null}>  $versions
     */
    public function select(array $versions, string $date): ?int
    {
        $best = null;
        foreach ($versions as $v) {
            if (! in_array($v['status'], [TimetableVersionStatus::Published->value, TimetableVersionStatus::Superseded->value], true)
                || $v['effective_from'] === null || $v['effective_from'] > $date
                || ($v['effective_to'] !== null && $v['effective_to'] < $date)) {
                continue;
            }
            if ($best === null || [$v['effective_from'], $v['published_at'] ?? ''] > [$best['effective_from'], $best['published_at'] ?? '']) {
                $best = $v;
            }
        }

        return $best['id'] ?? null;
    }
}
