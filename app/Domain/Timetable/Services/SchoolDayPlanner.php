<?php

namespace App\Domain\Timetable\Services;

use App\Domain\Timetable\ValueObjects\PeriodType;

/**
 * «توزيع الاستراحات تلقائياً»: lays the school day out from a start time and a lesson length.
 * Between lessons: 5-minute breaks, the main break (15) after the middle of the day and a
 * 10-minute break two lessons later — e.g. 7 lessons: 5 · 5 · 15 · 5 · 10 · 5.
 */
final class SchoolDayPlanner
{
    public const SHORT_BREAK = 5;

    public const MAIN_BREAK = 15;

    public const SECOND_BREAK = 10;

    /**
     * @return list<array{number: int, type: int, start: string, end: string}>|null  null when the day runs past midnight
     */
    public function plan(int $lessonCount, string $startTime, int $lessonMinutes): ?array
    {
        $minutes = PeriodTimeGuard::minutes($startTime);
        if ($minutes === null || $lessonCount < 1 || $lessonMinutes < 1) {
            return null;
        }

        $mainAfter = intdiv($lessonCount, 2);
        $day = [];
        for ($lesson = 1; $lesson <= $lessonCount; $lesson++) {
            $day[] = $this->slot(count($day) + 1, PeriodType::Lesson, $minutes, $lessonMinutes);
            $minutes += $lessonMinutes;
            if ($lesson === $lessonCount) {
                break;
            }
            $break = match (true) {
                $lesson === $mainAfter => self::MAIN_BREAK,
                $lesson === $mainAfter + 2 => self::SECOND_BREAK,
                default => self::SHORT_BREAK,
            };
            $day[] = $this->slot(count($day) + 1, PeriodType::Break, $minutes, $break);
            $minutes += $break;
        }

        return $minutes > 24 * 60 ? null : $day;
    }

    /** @return array{number: int, type: int, start: string, end: string} */
    private function slot(int $number, PeriodType $type, int $start, int $length): array
    {
        return ['number' => $number, 'type' => $type->value, 'start' => self::clock($start), 'end' => self::clock($start + $length)];
    }

    private static function clock(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
