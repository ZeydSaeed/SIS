<?php

namespace App\Domain\Timetable\Services;

use App\Domain\Timetable\Data\TimetableBoard;

/**
 * «توزيع تلقائي»: places a section's remaining lessons on free cells.
 *
 * Rules (the same the audit checks): the section and the teacher are free in the slot; a teacher
 * teaches at most the daily limit of the settings (default 6) lessons a day; a subject appears at
 * most the subject daily limit (default 2) times a day; practical subjects go in adjacent
 * pairs (a lone one only when an odd count is left).
 *
 * 1. Cell by cell: each day's next cell (right after its last lesson) takes a lesson whose teacher is
 *    free there — practical doubles first, then the subject least present that day, then the most
 *    left — so days stay packed (no idle period for the students) and subjects spread over the week.
 * 2. What no packed cell could take goes on any free cell of an allowed day.
 * 3. Holes left inside a day are closed by moving a later new lesson into them when its teacher is free.
 *
 * Deterministic: the same board gives the same plan.
 */
final class TimetableAutoPlacer
{
    /** @var array<string, true> "day:period" cells of the section */
    private array $sectionBusy = [];

    /** @var array<string, true> "teacher:day:period" */
    private array $teacherBusy = [];

    /** @var array<string, int> "teacher:day" → lessons */
    private array $teacherDay = [];

    /** @var array<string, int> "subject:day" → lessons of the section */
    private array $subjectDay = [];

    /** @var array<int, int> day → lessons of the section */
    private array $sectionDay = [];

    /** @var list<int> working days of the settings */
    private array $days = [];

    private int $maxTeacher = 0;

    private int $maxSubject = 0;

    /**
     * @return array{placements: list<array{subject_id: int, teacher_id: int, day: int, period_id: int}>, unplaced: int}
     */
    public function plan(TimetableBoard $board, int $sectionId): array
    {
        $this->load($board, $sectionId);
        $lessons = $this->remaining($board, $sectionId);
        $placements = [];

        $this->fillPackedCells($board, $lessons, $placements);

        $unplaced = 0;
        foreach ($lessons as &$lesson) {
            while ($lesson['left'] > 0) {
                $size = $lesson['practical'] && $lesson['left'] >= 2 ? 2 : 1;
                $slot = $this->anyFreeSlot($board, $lesson, $size) ?? ($size === 2 ? $this->anyFreeSlot($board, $lesson, 1) : null);
                if ($slot === null) {
                    $unplaced += $lesson['left'];
                    break;
                }
                $this->place($lesson, $slot[0], $slot[1], $placements);
            }
        }
        unset($lesson);

        return ['placements' => $this->compact($board, $placements), 'unplaced' => $unplaced];
    }

    private function load(TimetableBoard $board, int $sectionId): void
    {
        $this->days = $board->settings->days;
        $this->maxTeacher = $board->settings->maxTeacherPerDay;
        $this->maxSubject = $board->settings->maxSubjectPerDay;
        $this->sectionBusy = $this->teacherBusy = $this->teacherDay = $this->subjectDay = $this->sectionDay = [];
        foreach ($board->schedules as $s) {
            foreach (TimetableBoard::busyTeachers($s) as $teacherId) {
                $this->teacherBusy[$teacherId.':'.$s['day_of_week'].':'.$s['period_id']] = true;
                $this->teacherDay[$teacherId.':'.$s['day_of_week']] = ($this->teacherDay[$teacherId.':'.$s['day_of_week']] ?? 0) + 1;
            }
            if ($s['section_id'] === $sectionId) {
                $this->sectionBusy[$s['day_of_week'].':'.$s['period_id']] = true;
                $this->subjectDay[$s['subject_id'].':'.$s['day_of_week']] = ($this->subjectDay[$s['subject_id'].':'.$s['day_of_week']] ?? 0) + 1;
                $this->sectionDay[$s['day_of_week']] = ($this->sectionDay[$s['day_of_week']] ?? 0) + 1;
            }
        }
    }

    /** @return list<array{subject_id: int, teacher_id: int, left: int, practical: bool}> */
    private function remaining(TimetableBoard $board, int $sectionId): array
    {
        $placed = [];
        foreach ($board->schedules as $s) {
            if ($s['section_id'] === $sectionId) {
                $placed[$s['subject_id'].':'.$s['teacher_id']] = ($placed[$s['subject_id'].':'.$s['teacher_id']] ?? 0) + 1;
            }
        }

        $lessons = [];
        foreach ($board->requirements as $r) {
            if ($r['section_id'] !== $sectionId || $r['weekly'] === null) {
                continue;
            }
            $left = $r['weekly'] - ($placed[$r['subject_id'].':'.$r['teacher_id']] ?? 0);
            if ($left > 0) {
                $lessons[] = ['subject_id' => $r['subject_id'], 'teacher_id' => $r['teacher_id'], 'left' => $left, 'practical' => $board->isPractical($r['subject_id'])];
            }
        }
        usort($lessons, static fn (array $a, array $b): int => [$a['subject_id'], $a['teacher_id']] <=> [$b['subject_id'], $b['teacher_id']]);

        return $lessons;
    }

    /** Step 1: fill each day's next cell while some lesson fits there. */
    private function fillPackedCells(TimetableBoard $board, array &$lessons, array &$placements): void
    {
        $closed = [];
        do {
            $progress = false;
            $days = array_values(array_filter($this->days, static fn (int $day): bool => ! isset($closed[$day])));
            usort($days, fn (int $a, int $b): int => [$this->sectionDay[$a] ?? 0, $a] <=> [$this->sectionDay[$b] ?? 0, $b]);

            foreach ($days as $day) {
                $index = $this->packedIndex($board, $day);
                $order = array_keys($lessons);
                usort($order, fn (int $a, int $b): int => $this->candidateRank($lessons[$a], $day) <=> $this->candidateRank($lessons[$b], $day));

                $placedHere = false;
                foreach ($order as $i) {
                    $lesson = $lessons[$i];
                    if ($lesson['left'] <= 0) {
                        continue;
                    }
                    $size = $lesson['practical'] && $lesson['left'] >= 2 ? 2 : 1;
                    $run = $index === null ? null : $this->run($board, $index, $size);
                    if ($run !== null && $this->allowed($lesson, $day, $size) && $this->free($run, $day, $lesson['teacher_id'])) {
                        $this->place($lessons[$i], $day, $run, $placements);
                        $placedHere = $progress = true;
                        break;
                    }
                }
                if (! $placedHere) {
                    $closed[$day] = true;
                }
            }
        } while ($progress);
    }

    /** Practical doubles first, then the subject least present that day, then the most left. */
    private function candidateRank(array $lesson, int $day): array
    {
        $double = $lesson['practical'] && $lesson['left'] >= 2;

        return [$double ? 0 : 1, $this->subjectDay[$lesson['subject_id'].':'.$day] ?? 0, -$lesson['left'], $lesson['subject_id'], $lesson['teacher_id']];
    }

    /** The first lesson period after the section's last lesson that day (null when the day is full). */
    private function packedIndex(TimetableBoard $board, int $day): ?int
    {
        $next = 0;
        foreach ($board->lessonPeriodIds as $index => $periodId) {
            if (isset($this->sectionBusy[$day.':'.$periodId])) {
                $next = $index + 1;
            }
        }

        return $next < count($board->lessonPeriodIds) ? $next : null;
    }

    /** Step 2: any free cell of an allowed day (lightest day for the subject first). */
    private function anyFreeSlot(TimetableBoard $board, array $lesson, int $size): ?array
    {
        $days = array_values(array_filter($this->days, fn (int $day): bool => $this->allowed($lesson, $day, $size)));
        usort($days, fn (int $a, int $b): int => [$this->subjectDay[$lesson['subject_id'].':'.$a] ?? 0, $this->sectionDay[$a] ?? 0, $a]
            <=> [$this->subjectDay[$lesson['subject_id'].':'.$b] ?? 0, $this->sectionDay[$b] ?? 0, $b]);

        foreach ($days as $day) {
            foreach (array_keys($board->lessonPeriodIds) as $index) {
                $run = $this->run($board, $index, $size);
                if ($run !== null && $this->free($run, $day, $lesson['teacher_id'])) {
                    return [$day, $run];
                }
            }
        }

        return null;
    }

    private function allowed(array $lesson, int $day, int $size): bool
    {
        return ($this->subjectDay[$lesson['subject_id'].':'.$day] ?? 0) + $size <= $this->maxSubject
            && ($this->teacherDay[$lesson['teacher_id'].':'.$day] ?? 0) + $size <= $this->maxTeacher;
    }

    /** @return list<int>|null  the period (or the adjacent pair) starting at `index` */
    private function run(TimetableBoard $board, int $index, int $size): ?array
    {
        $periods = $board->lessonPeriodIds;
        $run = [$periods[$index]];
        if ($size === 2) {
            $next = $periods[$index + 1] ?? null;
            if ($next === null || ! $board->adjacent($periods[$index], $next)) {
                return null;
            }
            $run[] = $next;
        }

        return $run;
    }

    private function free(array $run, int $day, int $teacherId): bool
    {
        foreach ($run as $periodId) {
            if (isset($this->sectionBusy[$day.':'.$periodId]) || isset($this->teacherBusy[$teacherId.':'.$day.':'.$periodId])) {
                return false;
            }
        }

        return true;
    }

    /** @param  list<int>  $run */
    private function place(array &$lesson, int $day, array $run, array &$placements): void
    {
        foreach ($run as $periodId) {
            $placements[] = ['subject_id' => $lesson['subject_id'], 'teacher_id' => $lesson['teacher_id'], 'day' => $day, 'period_id' => $periodId];
            $this->sectionBusy[$day.':'.$periodId] = true;
            $this->teacherBusy[$lesson['teacher_id'].':'.$day.':'.$periodId] = true;
            $this->teacherDay[$lesson['teacher_id'].':'.$day] = ($this->teacherDay[$lesson['teacher_id'].':'.$day] ?? 0) + 1;
            $this->subjectDay[$lesson['subject_id'].':'.$day] = ($this->subjectDay[$lesson['subject_id'].':'.$day] ?? 0) + 1;
            $this->sectionDay[$day] = ($this->sectionDay[$day] ?? 0) + 1;
        }
        $lesson['left'] -= count($run);
    }

    /**
     * Step 3: closes idle periods inside the section's days — a newly placed (non-practical) lesson
     * after a hole moves into it when its teacher is free there. Existing lessons and doubles stay put.
     *
     * @param  list<array{subject_id: int, teacher_id: int, day: int, period_id: int}>  $placements
     * @return list<array{subject_id: int, teacher_id: int, day: int, period_id: int}>
     */
    private function compact(TimetableBoard $board, array $placements): array
    {
        $periods = $board->lessonPeriodIds;
        $position = array_flip($periods);

        foreach ($this->days as $day) {
            $moved = true;
            while ($moved) {
                $moved = false;
                $last = -1;
                foreach ($periods as $index => $periodId) {
                    if (isset($this->sectionBusy[$day.':'.$periodId])) {
                        $last = $index;
                    }
                }
                for ($gap = 0; $gap < $last && ! $moved; $gap++) {
                    if (isset($this->sectionBusy[$day.':'.$periods[$gap]])) {
                        continue;
                    }
                    // A later lesson of the same day, else the last lesson of another day (that day just ends earlier).
                    for ($i = count($placements) - 1; $i >= 0; $i--) {
                        $p = $placements[$i];
                        if (! $this->canFill($board, $p, $day, $periods[$gap], $position, $gap, $placements)) {
                            continue;
                        }
                        $this->move($placements[$i], $day, $periods[$gap]);
                        $moved = true;
                        break;
                    }
                    // Nobody can fill the hole: the day's last new lesson moves to the end of another day instead.
                    if (! $moved) {
                        $moved = $this->moveLastAway($board, $placements, $day, $periods[$last]);
                    }
                }
            }
        }

        return $placements;
    }

    /**
     * The new lesson sitting last in `day` moves to the packed cell of another day (teacher free, limits kept),
     * so `day` ends before its hole.
     *
     * @param  list<array{subject_id: int, teacher_id: int, day: int, period_id: int}>  $placements
     */
    private function moveLastAway(TimetableBoard $board, array &$placements, int $day, int $lastPeriod): bool
    {
        foreach ($placements as $i => $p) {
            if ($p['day'] !== $day || $p['period_id'] !== $lastPeriod || $this->inDouble($board, $placements, $p)) {
                continue;
            }
            foreach ($this->days as $other) {
                $index = $other === $day ? null : $this->packedIndex($board, $other);
                if ($index === null) {
                    continue;
                }
                $target = $board->lessonPeriodIds[$index];
                $lesson = ['subject_id' => $p['subject_id'], 'teacher_id' => $p['teacher_id']];
                $practicalClash = $board->isPractical($p['subject_id']) && ($this->subjectDay[$p['subject_id'].':'.$other] ?? 0) > 0;
                if (! $practicalClash && $this->allowed($lesson, $other, 1) && $this->free([$target], $other, $p['teacher_id'])) {
                    $this->move($placements[$i], $other, $target);

                    return true;
                }
            }
        }

        return false;
    }

    /** @param  array{subject_id: int, teacher_id: int, day: int, period_id: int}  $p */
    private function canFill(TimetableBoard $board, array $p, int $day, int $gapPeriod, array $position, int $gap, array $placements): bool
    {
        if ($this->inDouble($board, $placements, $p) || isset($this->teacherBusy[$p['teacher_id'].':'.$day.':'.$gapPeriod])) {
            return false;
        }
        if ($p['day'] === $day) {
            return $position[$p['period_id']] > $gap;
        }

        // From another day: only its last lesson, and only within the target day's limits.
        foreach ($board->lessonPeriodIds as $periodId) {
            if ($position[$periodId] > $position[$p['period_id']] && isset($this->sectionBusy[$p['day'].':'.$periodId])) {
                return false;
            }
        }

        // A lone practical only joins a day without that subject (it must not split from a twin there).
        if ($board->isPractical($p['subject_id']) && ($this->subjectDay[$p['subject_id'].':'.$day] ?? 0) > 0) {
            return false;
        }

        return ($this->subjectDay[$p['subject_id'].':'.$day] ?? 0) < $this->maxSubject
            && ($this->teacherDay[$p['teacher_id'].':'.$day] ?? 0) < $this->maxTeacher;
    }

    /**
     * A practical lesson glued to its twin (adjacent, same subject, same day) stays put; a lone one may move.
     *
     * @param  list<array{subject_id: int, teacher_id: int, day: int, period_id: int}>  $placements
     * @param  array{subject_id: int, teacher_id: int, day: int, period_id: int}  $p
     */
    private function inDouble(TimetableBoard $board, array $placements, array $p): bool
    {
        if (! $board->isPractical($p['subject_id'])) {
            return false;
        }
        foreach ($placements as $o) {
            if ($o['day'] === $p['day'] && $o['subject_id'] === $p['subject_id'] && $o['period_id'] !== $p['period_id']
                && ($board->adjacent($o['period_id'], $p['period_id']) || $board->adjacent($p['period_id'], $o['period_id']))) {
                return true;
            }
        }

        return false;
    }

    /** @param  array{subject_id: int, teacher_id: int, day: int, period_id: int}  $p */
    private function move(array &$p, int $day, int $periodId): void
    {
        unset($this->sectionBusy[$p['day'].':'.$p['period_id']], $this->teacherBusy[$p['teacher_id'].':'.$p['day'].':'.$p['period_id']]);
        if ($p['day'] !== $day) {
            foreach ([$p['day'] => -1, $day => 1] as $d => $delta) {
                $this->teacherDay[$p['teacher_id'].':'.$d] = ($this->teacherDay[$p['teacher_id'].':'.$d] ?? 0) + $delta;
                $this->subjectDay[$p['subject_id'].':'.$d] = ($this->subjectDay[$p['subject_id'].':'.$d] ?? 0) + $delta;
                $this->sectionDay[$d] = ($this->sectionDay[$d] ?? 0) + $delta;
            }
        }
        $p['day'] = $day;
        $p['period_id'] = $periodId;
        $this->sectionBusy[$day.':'.$periodId] = true;
        $this->teacherBusy[$p['teacher_id'].':'.$day.':'.$periodId] = true;
    }
}
