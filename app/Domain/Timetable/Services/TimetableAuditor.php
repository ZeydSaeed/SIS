<?php

namespace App\Domain\Timetable\Services;

use App\Domain\Timetable\Data\TimetableBoard;
use App\Domain\Timetable\Support\SchoolWeek;

/**
 * «تدقيق الجدول»: reviews the whole school-year grid and lists what is wrong, worst first.
 *
 * error   — the lesson cannot stand as it is (double booking, teacher no longer teaches it, lesson in a break);
 * warning — the timetable breaks a school rule (more lessons than the curriculum, overloaded teacher day,
 *           a subject repeated too often in a day, an idle period inside a section's day);
 * info    — work left or a quality hint (lessons still to place, a practical not taught as a double,
 *           a long wait between a teacher's lessons).
 *
 * Each issue: severity, code, and the place it points to (section / teacher / subject / day / period / schedules).
 */
final class TimetableAuditor
{
    private const SEVERITY_ORDER = ['error' => 0, 'warning' => 1, 'info' => 2];

    /**
     * @return list<array{severity: string, code: string, section_id: int|null, teacher_id: int|null, subject_id: int|null, day: int|null, period_id: int|null, schedule_ids: list<int>, count: int|null}>
     */
    public function audit(TimetableBoard $board): array
    {
        $issues = [
            ...$this->doubleBookings($board),
            ...$this->invalidLessons($board),
            ...$this->placementCounts($board),
            ...$this->teacherDays($board),
            ...$this->sectionDays($board),
        ];

        usort($issues, static fn (array $a, array $b): int => [self::SEVERITY_ORDER[$a['severity']], $a['code'], $a['section_id'] ?? 0, $a['day'] ?? 0]
            <=> [self::SEVERITY_ORDER[$b['severity']], $b['code'], $b['section_id'] ?? 0, $b['day'] ?? 0]);

        return $issues;
    }

    /** Two lessons of one teacher (or one section) in the same slot — only possible through old data. */
    private function doubleBookings(TimetableBoard $board): array
    {
        $issues = [];
        foreach (['teacher_id' => 'teacher_double_booked', 'section_id' => 'section_double_booked'] as $field => $code) {
            $bySlot = [];
            foreach ($board->schedules as $s) {
                $bySlot[$s[$field].':'.$s['day_of_week'].':'.$s['period_id']][] = $s;
            }
            foreach ($bySlot as $group) {
                if (count($group) > 1) {
                    $first = $group[0];
                    $issues[] = self::issue('error', $code, $first, ['schedule_ids' => array_column($group, 'id')]);
                }
            }
        }

        return $issues;
    }

    /** Lessons whose teacher is gone / no longer teaches the subject, or that sit in a break. */
    private function invalidLessons(TimetableBoard $board): array
    {
        $issues = [];
        $lessonPeriods = array_flip($board->lessonPeriodIds);
        $activeTeachers = array_flip($board->activeTeacherIds);
        foreach ($board->schedules as $s) {
            if (! isset($activeTeachers[$s['teacher_id']])) {
                $issues[] = self::issue('error', 'teacher_inactive', $s);
            } elseif (! isset($board->teacherSubjects[$s['teacher_id'].':'.$s['subject_id']])) {
                $issues[] = self::issue('error', 'teacher_not_assigned', $s);
            }
            if (! isset($lessonPeriods[$s['period_id']])) {
                $issues[] = self::issue('error', 'lesson_in_break', $s);
            }
        }

        return $issues;
    }

    /** Placed vs the curriculum's weekly lessons, per section · subject · teacher. */
    private function placementCounts(TimetableBoard $board): array
    {
        $placed = [];
        foreach ($board->schedules as $s) {
            $key = $s['section_id'].':'.$s['subject_id'].':'.$s['teacher_id'];
            $placed[$key][] = $s['id'];
        }

        $issues = [];
        foreach ($board->requirements as $r) {
            if ($r['weekly'] === null) {
                continue;
            }
            $ids = $placed[$r['section_id'].':'.$r['subject_id'].':'.$r['teacher_id']] ?? [];
            $diff = count($ids) - $r['weekly'];
            $where = ['section_id' => $r['section_id'], 'subject_id' => $r['subject_id'], 'teacher_id' => $r['teacher_id'], 'day_of_week' => null, 'period_id' => null, 'id' => null];
            if ($diff > 0) {
                $issues[] = self::issue('warning', 'lesson_over_placed', $where, ['schedule_ids' => $ids, 'count' => $diff]);
            } elseif ($diff < 0) {
                $issues[] = self::issue('info', 'lesson_under_placed', $where, ['count' => -$diff]);
            }
        }

        return $issues;
    }

    /** Overloaded teacher days and long waits between a teacher's lessons. */
    private function teacherDays(TimetableBoard $board): array
    {
        $byTeacherDay = [];
        foreach ($board->schedules as $s) {
            $byTeacherDay[$s['teacher_id'].':'.$s['day_of_week']][] = $s;
        }

        $issues = [];
        foreach ($byTeacherDay as $lessons) {
            $first = $lessons[0];
            $where = ['teacher_id' => $first['teacher_id'], 'day_of_week' => $first['day_of_week'], 'section_id' => null, 'subject_id' => null, 'period_id' => null, 'id' => null];
            if (count($lessons) > SchoolWeek::MAX_TEACHER_LESSONS_PER_DAY) {
                $issues[] = self::issue('warning', 'teacher_day_overload', $where, ['schedule_ids' => array_column($lessons, 'id'), 'count' => count($lessons)]);
            }
            $longest = $this->longestGap($board, array_column($lessons, 'period_id'));
            if ($longest >= 2) {
                $issues[] = self::issue('info', 'teacher_gap', $where, ['count' => $longest]);
            }
        }

        return $issues;
    }

    /** A subject repeated too often in a section's day, idle periods inside the day, practicals not taught as doubles. */
    private function sectionDays(TimetableBoard $board): array
    {
        $bySectionDay = [];
        foreach ($board->schedules as $s) {
            $bySectionDay[$s['section_id'].':'.$s['day_of_week']][] = $s;
        }

        $issues = [];
        foreach ($bySectionDay as $lessons) {
            $first = $lessons[0];
            $bySubject = [];
            foreach ($lessons as $s) {
                $bySubject[$s['subject_id']][] = $s;
            }
            foreach ($bySubject as $subjectId => $same) {
                $where = ['section_id' => $first['section_id'], 'day_of_week' => $first['day_of_week'], 'subject_id' => $subjectId, 'teacher_id' => $same[0]['teacher_id'], 'period_id' => null, 'id' => null];
                if (count($same) > SchoolWeek::MAX_SUBJECT_LESSONS_PER_DAY) {
                    $issues[] = self::issue('warning', 'subject_day_repeat', $where, ['schedule_ids' => array_column($same, 'id'), 'count' => count($same)]);
                }
                if ($board->isPractical($subjectId) && ! $this->taughtAsDoubles($board, array_column($same, 'period_id'))) {
                    $issues[] = self::issue('info', 'practical_split', $where, ['schedule_ids' => array_column($same, 'id')]);
                }
            }

            $gaps = $this->longestGap($board, array_column($lessons, 'period_id'));
            if ($gaps >= 1) {
                $issues[] = self::issue('warning', 'section_day_gap', ['section_id' => $first['section_id'], 'day_of_week' => $first['day_of_week'], 'teacher_id' => null, 'subject_id' => null, 'period_id' => null, 'id' => null], ['count' => $gaps]);
            }
        }

        return $issues;
    }

    /** Longest run of free lesson periods between the first and the last occupied one. */
    private function longestGap(TimetableBoard $board, array $periodIds): int
    {
        $positions = [];
        foreach ($periodIds as $periodId) {
            $index = array_search($periodId, $board->lessonPeriodIds, true);
            if ($index !== false) {
                $positions[$index] = true;
            }
        }
        if (count($positions) < 2) {
            return 0;
        }
        ksort($positions);
        $indexes = array_keys($positions);
        $longest = 0;
        for ($i = 1, $n = count($indexes); $i < $n; $i++) {
            $longest = max($longest, $indexes[$i] - $indexes[$i - 1] - 1);
        }

        return $longest;
    }

    /**
     * A practical's lessons of one day form a single unbroken run (a double, no break between).
     * A lone practical lesson is fine — an odd weekly count leaves one.
     */
    private function taughtAsDoubles(TimetableBoard $board, array $periodIds): bool
    {
        $ordered = array_values(array_filter($board->lessonPeriodIds, static fn (int $id): bool => in_array($id, $periodIds, true)));
        for ($i = 1, $n = count($ordered); $i < $n; $i++) {
            if (! $board->adjacent($ordered[$i - 1], $ordered[$i])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array{id?: int|null, section_id?: int|null, teacher_id?: int|null, subject_id?: int|null, day_of_week?: int|null, period_id?: int|null}  $at
     * @param  array{schedule_ids?: list<int>, count?: int}  $extra
     * @return array{severity: string, code: string, section_id: int|null, teacher_id: int|null, subject_id: int|null, day: int|null, period_id: int|null, schedule_ids: list<int>, count: int|null}
     */
    private static function issue(string $severity, string $code, array $at, array $extra = []): array
    {
        return [
            'severity' => $severity,
            'code' => $code,
            'section_id' => $at['section_id'] ?? null,
            'teacher_id' => $at['teacher_id'] ?? null,
            'subject_id' => $at['subject_id'] ?? null,
            'day' => $at['day_of_week'] ?? null,
            'period_id' => $at['period_id'] ?? null,
            'schedule_ids' => $extra['schedule_ids'] ?? (isset($at['id']) ? [(int) $at['id']] : []),
            'count' => $extra['count'] ?? null,
        ];
    }
}
