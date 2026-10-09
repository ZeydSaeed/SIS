<?php

namespace App\Domain\Timetable\Testing;

use App\Domain\Timetable\Data\TimetableBoard;
use App\Domain\Timetable\Services\PeriodTimeGuard;
use App\Domain\Timetable\ValueObjects\PeriodType;

/**
 * The school day and the week: lesson lengths, unexplained gaps, breaks at the edges of the day or back to back,
 * long runs without a break, no main break; then how evenly each section's and teacher's lessons spread over
 * the working days.
 */
final class DayChecks
{
    private const SHORT_LESSON = 30;

    private const LONG_LESSON = 90;

    private const RUN_WITHOUT_BREAK = 4;

    private const MAIN_BREAK = 15;

    private const UNBALANCED_SPREAD = 3;

    /** @return list<array<string, mixed>> */
    public function run(TimetableBoard $board, RemedyFinder $remedies): array
    {
        return [...$this->periods($board, $remedies), ...$this->balance($board)];
    }

    /** @return list<array<string, mixed>> */
    private function periods(TimetableBoard $board, RemedyFinder $remedies): array
    {
        $day = $remedies->ordered();
        if ($day === []) {
            return [];
        }
        $issues = [];
        $lengths = [];
        $run = 0;
        $longestRun = 0;
        $mainBreak = false;
        foreach ($day as $i => $p) {
            $minutes = RemedyFinder::length($p);
            if ($p->periodType === PeriodType::Lesson->value) {
                $lengths[$minutes] = true;
                $run++;
                $longestRun = max($longestRun, $run);
                if ($minutes < self::SHORT_LESSON || $minutes > self::LONG_LESSON) {
                    $issues[] = TestIssue::make('input', TestIssue::WARNING, 'periods', 'lesson_length_unusual', ['period_id' => $p->id, 'count' => $minutes], 'lesson_length',
                        [TestIssue::fix('resize_period', 'reshape_day', ['operation' => 'resize', 'period_id' => $p->id, 'minutes' => 45], false)]);
                }
            } else {
                $mainBreak = $mainBreak || $minutes >= self::MAIN_BREAK;
                $run = $minutes >= 10 ? 0 : $run;
                if ($i === 0 || $i === count($day) - 1) {
                    $issues[] = TestIssue::make('input', TestIssue::WARNING, 'breaks', 'break_at_day_edge', ['period_id' => $p->id], 'break_outside_lessons',
                        [TestIssue::fix('remove_break', 'reshape_day', ['operation' => 'remove_break', 'period_id' => $p->id], false)]);
                } elseif ($day[$i - 1]->periodType !== PeriodType::Lesson->value) {
                    $issues[] = TestIssue::make('input', TestIssue::SUGGESTION, 'breaks', 'consecutive_breaks', ['period_id' => $p->id], 'breaks_back_to_back',
                        [TestIssue::fix('merge_breaks', 'reshape_day', ['operation' => 'remove_break', 'period_id' => $p->id], false)]);
                }
            }
            $next = $day[$i + 1] ?? null;
            if ($next !== null && (int) PeriodTimeGuard::minutes($next->startTime) > (int) PeriodTimeGuard::minutes($p->endTime)) {
                $issues[] = TestIssue::make('input', TestIssue::SUGGESTION, 'periods', 'unexplained_gap', ['period_id' => $next->id,
                    'count' => (int) PeriodTimeGuard::minutes($next->startTime) - (int) PeriodTimeGuard::minutes($p->endTime)],
                    'gap_without_break', [TestIssue::fix('close_gaps', 'reshape_day', ['operation' => 'fixed_pattern'], false)]);
            }
        }
        if (count($lengths) > 1) {
            $issues[] = TestIssue::make('input', TestIssue::SUGGESTION, 'periods', 'lesson_lengths_vary', ['count' => count($lengths)], 'variable_timing',
                [TestIssue::fix('fixed_pattern', 'reshape_day', ['operation' => 'fixed_pattern'], false)]);
        }
        if ($longestRun > self::RUN_WITHOUT_BREAK) {
            $issues[] = TestIssue::make('input', TestIssue::SUGGESTION, 'breaks', 'long_run_without_break', ['count' => $longestRun, 'limit' => self::RUN_WITHOUT_BREAK], 'students_need_rest',
                [TestIssue::fix('insert_break', 'reshape_day', ['operation' => 'insert_break', 'after_period_id' => $board->lessonPeriodIds[intdiv(count($board->lessonPeriodIds), 2) - 1] ?? null, 'minutes' => self::MAIN_BREAK], false)]);
        }
        if (! $mainBreak && count($board->lessonPeriodIds) >= 5) {
            $issues[] = TestIssue::make('input', TestIssue::SUGGESTION, 'breaks', 'no_main_break', ['count' => count($board->lessonPeriodIds)], 'long_day_no_main_break',
                [TestIssue::fix('insert_break', 'reshape_day', ['operation' => 'insert_break', 'after_period_id' => $board->lessonPeriodIds[intdiv(count($board->lessonPeriodIds), 2) - 1] ?? null, 'minutes' => self::MAIN_BREAK], false)]);
        }

        return $issues;
    }

    /** @return list<array<string, mixed>> */
    private function balance(TimetableBoard $board): array
    {
        $days = $board->settings->days;
        $section = [];
        $teacher = [];
        foreach ($board->schedules as $s) {
            $section[$s['section_id']][$s['day_of_week']] = ($section[$s['section_id']][$s['day_of_week']] ?? 0) + 1;
            foreach (TimetableBoard::busyTeachers($s) as $t) {
                $teacher[$t][$s['day_of_week']] = ($teacher[$t][$s['day_of_week']] ?? 0) + 1;
            }
        }
        $issues = [];
        foreach (['section' => $section, 'teacher' => $teacher] as $kind => $loads) {
            foreach ($loads as $id => $byDay) {
                $counts = array_map(static fn (int $d): int => $byDay[$d] ?? 0, $days);
                $spread = max($counts) - min($counts);
                if ($spread >= self::UNBALANCED_SPREAD) {
                    $issues[] = TestIssue::make('grid', TestIssue::OPTIMIZATION, 'days', $kind.'_days_unbalanced', [$kind.'_id' => $id, 'count' => max($counts), 'limit' => min($counts)],
                        'uneven_week', [TestIssue::fix('optimize', 'generate', ['mode' => 4, $kind.'_id' => $id], true)]);
                }
            }
        }

        return $issues;
    }
}
