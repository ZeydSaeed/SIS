<?php

namespace App\Domain\Timetable\Services;

use App\Domain\Timetable\Data\TimetableBoard;
use App\Domain\Timetable\Support\DayRuns;

/**
 * «جودة الجدول»: how good the placed timetable is — kept apart from «are all lessons placed».
 *
 * feasible  — no audit error stands (no double booking, no invalid lesson);
 * metrics   — each a percentage, null when there is nothing to measure:
 *   completeness       placed / required lessons (each requirement counted up to its weekly load)
 *   teacher_compactness 1 − free periods inside teachers' days / their lessons
 *   section_compactness 1 − free periods inside sections' days / their lessons
 *   distribution       section-subject-days within the daily subject limit
 *   practical_doubles  practical section-days taught as one unbroken run
 *   workload_balance   teacher-days within the daily lesson limit
 * overall   — the mean of the measured metrics;
 * grade     — `infeasible` (errors) → `incomplete` (lessons left) → `complete`.
 *
 * The score describes the timetable; it is not a target to optimise blindly.
 */
final class TimetableQualityScorer
{
    /**
     * @param  list<array{severity: string}>  $issues  the audit of the same board ({@see TimetableAuditor})
     * @return array{feasible: bool, grade: string, overall: int|null, errors: int, warnings: int, metrics: array<string, int|null>, counts: array{required: int, placed: int, teacher_gaps: int, section_gaps: int}}
     */
    public function score(TimetableBoard $board, array $issues): array
    {
        $errors = count(array_filter($issues, static fn (array $i): bool => $i['severity'] === 'error'));
        $warnings = count(array_filter($issues, static fn (array $i): bool => $i['severity'] === 'warning'));

        $placedByLesson = $teacherDays = $sectionDays = $subjectDays = [];
        foreach ($board->schedules as $s) {
            $key = $s['section_id'].':'.$s['subject_id'].':'.$s['teacher_id'];
            $placedByLesson[$key] = ($placedByLesson[$key] ?? 0) + 1;
            foreach (TimetableBoard::busyTeachers($s) as $teacherId) {
                $teacherDays[$teacherId.':'.$s['day_of_week']][] = $s['period_id'];
            }
            $sectionDays[$s['section_id'].':'.$s['day_of_week']][] = $s['period_id'];
            $subjectDays[$s['section_id'].':'.$s['day_of_week'].':'.$s['subject_id']][] = $s['period_id'];
        }

        $required = $placed = 0;
        foreach ($board->requirements as $r) {
            if ($r['weekly'] !== null) {
                $required += $r['weekly'];
                $placed += min($r['weekly'], $placedByLesson[$r['section_id'].':'.$r['subject_id'].':'.$r['teacher_id']] ?? 0);
            }
        }

        [$teacherGaps, $teacherLessons] = $this->gaps($board, $teacherDays);
        [$sectionGaps, $sectionLessons] = $this->gaps($board, $sectionDays);

        $withinSubjectLimit = $practicalDays = $practicalDoubles = 0;
        foreach ($subjectDays as $key => $periodIds) {
            if (count($periodIds) <= $board->settings->maxSubjectPerDay) {
                $withinSubjectLimit++;
            }
            $subjectId = (int) substr($key, strrpos($key, ':') + 1);
            if ($board->isPractical($subjectId)) {
                $practicalDays++;
                $positions = DayRuns::positions($board->lessonPeriodIds, $periodIds);
                if ($this->unbroken($board, $positions)) {
                    $practicalDoubles++;
                }
            }
        }

        $teacherDaysWithinLimit = count(array_filter($teacherDays, static fn (array $p): bool => count($p) <= $board->settings->maxTeacherPerDay));

        $metrics = [
            'completeness' => self::percent($placed, $required),
            'teacher_compactness' => self::inverse($teacherGaps, $teacherLessons),
            'section_compactness' => self::inverse($sectionGaps, $sectionLessons),
            'distribution' => self::percent($withinSubjectLimit, count($subjectDays)),
            'practical_doubles' => self::percent($practicalDoubles, $practicalDays),
            'workload_balance' => self::percent($teacherDaysWithinLimit, count($teacherDays)),
        ];
        $measured = array_filter($metrics, static fn (?int $v): bool => $v !== null);

        return [
            'feasible' => $errors === 0,
            'grade' => match (true) {
                $errors > 0 => 'infeasible',
                $placed < $required => 'incomplete',
                default => 'complete',
            },
            'overall' => $measured === [] ? null : (int) round(array_sum($measured) / count($measured)),
            'errors' => $errors,
            'warnings' => $warnings,
            'metrics' => $metrics,
            'counts' => ['required' => $required, 'placed' => $placed, 'teacher_gaps' => $teacherGaps, 'section_gaps' => $sectionGaps],
        ];
    }

    /**
     * @param  array<string, list<int>>  $days  owner:day → periods
     * @return array{int, int} free periods inside the days, lessons
     */
    private function gaps(TimetableBoard $board, array $days): array
    {
        $gaps = $lessons = 0;
        foreach ($days as $periodIds) {
            $positions = DayRuns::positions($board->lessonPeriodIds, $periodIds);
            $gaps += DayRuns::gaps($positions);
            $lessons += count($positions);
        }

        return [$gaps, $lessons];
    }

    /** One unbroken run: consecutive positions joined by a short changeover (the main break splits it). */
    private function unbroken(TimetableBoard $board, array $positions): bool
    {
        for ($i = 1, $n = count($positions); $i < $n; $i++) {
            if ($positions[$i] !== $positions[$i - 1] + 1
                || ! $board->adjacent($board->lessonPeriodIds[$positions[$i - 1]], $board->lessonPeriodIds[$positions[$i]])) {
                return false;
            }
        }

        return true;
    }

    private static function percent(int $part, int $whole): ?int
    {
        return $whole === 0 ? null : (int) floor(100 * $part / $whole);
    }

    private static function inverse(int $bad, int $whole): ?int
    {
        return $whole === 0 ? null : max(0, (int) floor(100 * (1 - $bad / $whole)));
    }
}
