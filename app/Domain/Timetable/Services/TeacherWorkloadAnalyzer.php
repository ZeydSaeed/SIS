<?php

namespace App\Domain\Timetable\Services;

use App\Domain\Timetable\Data\TimetableBoard;
use App\Domain\Timetable\Support\DayRuns;

/**
 * «عبء المدرسين»: per teacher, the weekly load the assignments ask for against what the grid holds.
 *
 * required — weekly lessons of the teacher's assignments (curriculum hours; unknown loads not counted);
 * placed   — active lessons on the grid, split into practical / theory, and per day;
 * gaps     — free periods between a teacher's first and last lesson, summed over the week;
 * capacity — lessons the week allows (days × the daily limit, at most the lesson slots, at most the teacher's
 *            personal weekly maximum when one is set).
 *
 * Status: `over` when the load passes the capacity or a day passes the daily limit, `incomplete` while
 * fewer lessons are placed than required, `ok` otherwise. No underload threshold — SIS has no policy for it.
 */
final class TeacherWorkloadAnalyzer
{
    /**
     * @return list<array{teacher_id: int, required: int, placed: int, practical: int, theory: int, sections: int, by_day: array<int, int>, max_day: int, gaps: int, max_consecutive: int, capacity: int, status: string}>
     */
    public function analyze(TimetableBoard $board): array
    {
        $days = $board->settings->days;

        $rows = [];
        $row = static fn (int $teacherId): array => [
            'teacher_id' => $teacherId, 'required' => 0, 'placed' => 0, 'practical' => 0, 'theory' => 0,
            'sections' => [], 'periods' => array_fill_keys($days, []),
        ];

        foreach ($board->requirements as $r) {
            $rows[$r['teacher_id']] ??= $row($r['teacher_id']);
            $rows[$r['teacher_id']]['required'] += $r['weekly'] ?? 0;
            $rows[$r['teacher_id']]['sections'][$r['section_id']] = true;
        }
        foreach ($board->schedules as $s) {
            // Lead and co-teacher; joined-class rows keep the teacher busy once (on the lead row).
            foreach (TimetableBoard::busyTeachers($s) as $teacherId) {
                $rows[$teacherId] ??= $row($teacherId);
                $rows[$teacherId]['placed']++;
                $rows[$teacherId][$board->isPractical($s['subject_id']) ? 'practical' : 'theory']++;
                $rows[$teacherId]['periods'][$s['day_of_week']][] = $s['period_id'];
            }
            $rows[$s['teacher_id']] ??= $row($s['teacher_id']);
            $rows[$s['teacher_id']]['sections'][$s['section_id']] = true;
        }

        $result = [];
        foreach ($rows as $r) {
            $byDay = [];
            $gaps = $longest = 0;
            foreach ($r['periods'] as $day => $periodIds) {
                $byDay[$day] = count($periodIds);
                $positions = DayRuns::positions($board->lessonPeriodIds, $periodIds);
                $gaps += DayRuns::gaps($positions);
                $longest = max($longest, DayRuns::longestRun($positions));
            }
            $maxDay = $byDay === [] ? 0 : max($byDay);
            $capacity = $board->weeklyCapacity($r['teacher_id']);

            $result[] = [
                'teacher_id' => $r['teacher_id'],
                'required' => $r['required'],
                'placed' => $r['placed'],
                'practical' => $r['practical'],
                'theory' => $r['theory'],
                'sections' => count($r['sections']),
                'by_day' => $byDay,
                'max_day' => $maxDay,
                'gaps' => $gaps,
                'max_consecutive' => $longest,
                'capacity' => $capacity,
                'status' => match (true) {
                    $r['required'] > $capacity || $r['placed'] > $capacity || $maxDay > $board->dailyLimit($r['teacher_id']) => 'over',
                    $r['placed'] < $r['required'] => 'incomplete',
                    default => 'ok',
                },
            ];
        }

        usort($result, static fn (array $a, array $b): int => [$b['required'], $a['teacher_id']] <=> [$a['required'], $b['teacher_id']]);

        return $result;
    }
}
