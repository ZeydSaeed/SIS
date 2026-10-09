<?php

namespace App\Domain\Timetable\Services;

use App\Domain\Timetable\Data\PeriodSnapshot;
use App\Domain\Timetable\ValueObjects\PeriodPresentation;
use App\Domain\Timetable\ValueObjects\PeriodType;

/**
 * The school day as a row of periods, re-timed automatically («تزحيف»). Every operation returns the whole new day
 * (in order, renumbered 1…n — breaks keep a number) or an error code; nothing is persisted here.
 *
 * - insertBreak  a break after a period (or before the first): everything after it moves later by its length.
 * - resize       a period / break gets a new length: everything after it moves by the difference.
 * - moveBreak    a break goes after another period: the day closes behind it and opens where it lands.
 * - removeBreak  a break leaves the day: everything after it moves earlier by its length (the row is retired).
 * - retime       one period gets a new start / end; with `cascade` the following periods move with its end.
 * - fixedPattern every lesson gets the same length from the day's start; breaks keep their lengths; gaps close.
 *
 * Gaps between periods are kept by the shifting operations (only `fixedPattern` closes them). Lesson periods keep
 * their ids, so lessons placed on them stay; the day never runs past midnight; at most 20 periods.
 */
final class BellScheduleCalculator
{
    public const MAX_PERIODS = 20;

    public const MIN_LESSON = 5;

    public const MAX_LESSON = 180;

    public const MIN_BREAK = 1;

    public const MAX_BREAK = 180;

    private const DAY_END = 24 * 60;

    /**
     * @param  list<PeriodSnapshot>  $day
     * @return list<array{id: int|null, number: int, type: int, start: string, end: string, presentation: PeriodPresentation|null}>|string
     */
    public function insertBreak(array $day, ?int $afterPeriodId, int $minutes, PeriodPresentation $presentation): array|string
    {
        if ($minutes < self::MIN_BREAK || $minutes > self::MAX_BREAK) {
            return 'timetable.break_minutes_invalid';
        }
        $slots = self::slots($day);
        if (count($slots) >= self::MAX_PERIODS) {
            return 'timetable.period_limit_reached';
        }
        $at = $afterPeriodId === null ? 0 : self::indexOf($slots, $afterPeriodId);
        if ($at === null) {
            return 'timetable.period_not_in_school';
        }
        if ($afterPeriodId !== null) {
            $at++;
        }
        $start = $afterPeriodId === null ? ($slots[0]['start'] ?? 8 * 60) : $slots[$at - 1]['end'];
        $slots = self::shiftFrom($slots, $at, $minutes);
        array_splice($slots, $at, 0, [['id' => null, 'type' => PeriodType::Break->value, 'start' => $start, 'end' => $start + $minutes, 'presentation' => $presentation]]);

        return self::finish($slots);
    }

    /** @param  list<PeriodSnapshot>  $day */
    public function resize(array $day, int $periodId, int $minutes): array|string
    {
        $slots = self::slots($day);
        $at = self::indexOf($slots, $periodId);
        if ($at === null) {
            return 'timetable.period_not_in_school';
        }
        $error = self::lengthError($slots[$at]['type'], $minutes);
        if ($error !== null) {
            return $error;
        }
        $delta = $minutes - ($slots[$at]['end'] - $slots[$at]['start']);
        $slots[$at]['end'] += $delta;
        $slots = self::shiftFrom($slots, $at + 1, $delta);

        return self::finish($slots);
    }

    /** @param  list<PeriodSnapshot>  $day */
    public function moveBreak(array $day, int $breakId, ?int $afterPeriodId): array|string
    {
        if ($breakId === $afterPeriodId) {
            return 'timetable.break_move_invalid';
        }
        $slots = self::slots($day);
        $at = self::indexOf($slots, $breakId);
        if ($at === null || $slots[$at]['type'] !== PeriodType::Break->value) {
            return 'timetable.break_not_found';
        }
        if ($afterPeriodId !== null && self::indexOf($slots, $afterPeriodId) === null) {
            return 'timetable.period_not_in_school';
        }
        $break = $slots[$at];
        $length = $break['end'] - $break['start'];
        $slots = self::shiftFrom($slots, $at + 1, -$length);
        array_splice($slots, $at, 1);

        $to = $afterPeriodId === null ? 0 : (int) self::indexOf($slots, $afterPeriodId) + 1;
        $start = $afterPeriodId === null ? ($slots[0]['start'] ?? $break['start']) : $slots[$to - 1]['end'];
        $slots = self::shiftFrom($slots, $to, $length);
        array_splice($slots, $to, 0, [['id' => $break['id'], 'type' => $break['type'], 'start' => $start, 'end' => $start + $length, 'presentation' => null]]);

        return self::finish($slots);
    }

    /**
     * @param  list<PeriodSnapshot>  $day
     * @return array{day: list<array<string, mixed>>, retired: int}|string
     */
    public function removeBreak(array $day, int $breakId): array|string
    {
        $slots = self::slots($day);
        $at = self::indexOf($slots, $breakId);
        if ($at === null || $slots[$at]['type'] !== PeriodType::Break->value) {
            return 'timetable.break_not_found';
        }
        $slots = self::shiftFrom($slots, $at + 1, -($slots[$at]['end'] - $slots[$at]['start']));
        array_splice($slots, $at, 1);
        $plan = self::finish($slots);

        return is_string($plan) ? $plan : ['day' => $plan, 'retired' => $breakId];
    }

    /** @param  list<PeriodSnapshot>  $day */
    public function retime(array $day, int $periodId, string $startTime, string $endTime, bool $cascade): array|string
    {
        $start = PeriodTimeGuard::minutes($startTime);
        $end = PeriodTimeGuard::minutes($endTime);
        if ($start === null || $end === null || $start >= $end) {
            return 'timetable.period_time_invalid';
        }
        $slots = self::slots($day);
        $at = self::indexOf($slots, $periodId);
        if ($at === null) {
            return 'timetable.period_not_in_school';
        }
        $error = self::lengthError($slots[$at]['type'], $end - $start);
        if ($error !== null) {
            return $error;
        }
        $delta = $end - $slots[$at]['end'];
        $slots[$at]['start'] = $start;
        $slots[$at]['end'] = $end;
        if ($cascade) {
            $slots = self::shiftFrom($slots, $at + 1, $delta);
        }

        return self::finish($slots);
    }

    /** @param  list<PeriodSnapshot>  $day */
    public function fixedPattern(array $day, string $dayStart, int $lessonMinutes): array|string
    {
        $cursor = PeriodTimeGuard::minutes($dayStart);
        if ($cursor === null) {
            return 'timetable.period_time_invalid';
        }
        $error = self::lengthError(PeriodType::Lesson->value, $lessonMinutes);
        if ($error !== null) {
            return $error;
        }
        $slots = self::slots($day);
        if ($slots === []) {
            return 'timetable.arrange_no_lessons';
        }
        foreach ($slots as $i => $slot) {
            $length = $slot['type'] === PeriodType::Lesson->value ? $lessonMinutes : $slot['end'] - $slot['start'];
            $slots[$i]['start'] = $cursor;
            $slots[$i]['end'] = $cursor + $length;
            $cursor += $length;
        }

        return self::finish($slots);
    }

    /** @return string|null error code */
    private static function lengthError(int $type, int $minutes): ?string
    {
        if ($type === PeriodType::Lesson->value) {
            return $minutes < self::MIN_LESSON || $minutes > self::MAX_LESSON ? 'timetable.lesson_minutes_invalid' : null;
        }

        return $minutes < self::MIN_BREAK || $minutes > self::MAX_BREAK ? 'timetable.break_minutes_invalid' : null;
    }

    /**
     * @param  list<PeriodSnapshot>  $day
     * @return list<array{id: int|null, type: int, start: int, end: int, presentation: PeriodPresentation|null}>
     */
    private static function slots(array $day): array
    {
        usort($day, static fn (PeriodSnapshot $a, PeriodSnapshot $b): int => [PeriodTimeGuard::minutes($a->startTime), $a->periodNumber] <=> [PeriodTimeGuard::minutes($b->startTime), $b->periodNumber]);

        return array_map(static fn (PeriodSnapshot $p): array => [
            'id' => $p->id,
            'type' => $p->periodType,
            'start' => (int) PeriodTimeGuard::minutes($p->startTime),
            'end' => (int) PeriodTimeGuard::minutes($p->endTime),
            'presentation' => null,
        ], $day);
    }

    /** @param  list<array<string, mixed>>  $slots */
    private static function indexOf(array $slots, int $id): ?int
    {
        foreach ($slots as $i => $slot) {
            if ($slot['id'] === $id) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $slots
     * @return list<array<string, mixed>>
     */
    private static function shiftFrom(array $slots, int $from, int $delta): array
    {
        for ($i = $from, $n = count($slots); $i < $n; $i++) {
            $slots[$i]['start'] += $delta;
            $slots[$i]['end'] += $delta;
        }

        return $slots;
    }

    /**
     * @param  list<array{id: int|null, type: int, start: int, end: int, presentation: PeriodPresentation|null}>  $slots
     * @return list<array{id: int|null, number: int, type: int, start: string, end: string, presentation: PeriodPresentation|null}>|string
     */
    private static function finish(array $slots): array|string
    {
        $previousEnd = 0;
        $out = [];
        foreach ($slots as $i => $slot) {
            if ($slot['start'] < 0 || $slot['end'] > self::DAY_END) {
                return 'timetable.day_past_midnight';
            }
            if ($slot['start'] >= $slot['end'] || $slot['start'] < $previousEnd) {
                return 'timetable.period_overlap';
            }
            $previousEnd = $slot['end'];
            $out[] = [
                'id' => $slot['id'],
                'number' => $i + 1,
                'type' => $slot['type'],
                'start' => self::clock($slot['start']),
                'end' => self::clock($slot['end']),
                'presentation' => $slot['presentation'],
            ];
        }

        return $out;
    }

    private static function clock(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
