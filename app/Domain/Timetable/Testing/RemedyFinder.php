<?php

namespace App\Domain\Timetable\Testing;

use App\Domain\Timetable\Data\PeriodSnapshot;
use App\Domain\Timetable\Data\TimetableBoard;
use App\Domain\Timetable\Services\PeriodTimeGuard;
use App\Domain\Timetable\ValueObjects\AvailabilityKind;
use App\Domain\Timetable\ValueObjects\PeriodType;

/**
 * «المساعد الذكي» of the timetable test: an expert system over the board (no external model, no learning), so
 * every proposal is explainable and reproducible. It never writes anything — it computes concrete remedies
 * (a free slot, a free room, a qualified teacher with spare load, a break to shorten) with the reason they work.
 */
final class RemedyFinder
{
    /** @var array<string, true> "T12:3:45" / "S7:3:45" / "R4:3:45" busy (any week) */
    private array $busy = [];

    /** @var array<string, true> hard unavailability (time-off) in the same key space */
    private array $off = [];

    /** @var array<int, array<int, int>> section → day → lessons */
    private array $sectionDayLoad = [];

    /** @var array<int, int> teacher → weekly lessons required */
    private array $teacherLoad = [];

    public function __construct(private readonly TimetableBoard $board)
    {
        foreach ($board->schedules as $s) {
            $slot = ':'.$s['day_of_week'].':'.$s['period_id'];
            foreach (TimetableBoard::busyTeachers($s) as $t) {
                $this->busy['T'.$t.$slot] = true;
            }
            $this->busy['S'.$s['section_id'].$slot] = true;
            if (($s['room_id'] ?? null) !== null && ($s['joined_to'] ?? null) === null) {
                $this->busy['R'.$s['room_id'].$slot] = true;
            }
            $this->sectionDayLoad[$s['section_id']][$s['day_of_week']] = ($this->sectionDayLoad[$s['section_id']][$s['day_of_week']] ?? 0) + 1;
        }
        foreach ($board->availability as $a) {
            if ($a['kind'] !== AvailabilityKind::Unavailable->value) {
                continue;
            }
            $slot = ':'.$a['day'].':'.$a['period_id'];
            foreach (['T' => $a['teacher_id'], 'S' => $a['section_id'], 'R' => $a['room_id']] as $prefix => $id) {
                if ($id !== null) {
                    $this->off[$prefix.$id.$slot] = true;
                }
            }
        }
        foreach ($board->requirements as $r) {
            $this->teacherLoad[$r['teacher_id']] = ($this->teacherLoad[$r['teacher_id']] ?? 0) + (int) $r['weekly'];
        }
    }

    /**
     * Free slots where the lesson can move: its section, teacher(s) and room are free and not on time-off.
     * Ranked: the section's lighter days first, then slots next to its other lessons (no new gap), then earlier.
     *
     * @param  array<string, mixed>  $lesson
     * @return list<array{day: int, period_id: int, reason: string}>
     */
    public function freeSlots(array $lesson, int $limit = 3): array
    {
        $candidates = [];
        foreach ($this->board->settings->days as $day) {
            foreach ($this->board->lessonPeriodIds as $index => $periodId) {
                if ($day === $lesson['day_of_week'] && $periodId === $lesson['period_id']) {
                    continue;
                }
                if (! $this->lessonFits($lesson, $day, $periodId)) {
                    continue;
                }
                $load = $this->sectionDayLoad[$lesson['section_id']][$day] ?? 0;
                $neighbour = $this->isBusy('S', $lesson['section_id'], $day, $this->board->lessonPeriodIds[$index - 1] ?? 0)
                    || $this->isBusy('S', $lesson['section_id'], $day, $this->board->lessonPeriodIds[$index + 1] ?? 0);
                $candidates[] = ['day' => $day, 'period_id' => $periodId, 'score' => $load * 10 + ($neighbour ? 0 : 5) + $index,
                    'reason' => $neighbour ? 'slot_next_to_lessons' : 'slot_light_day'];
            }
        }
        usort($candidates, static fn (array $a, array $b): int => [$a['score'], $a['day'], $a['period_id']] <=> [$b['score'], $b['day'], $b['period_id']]);

        return array_map(static fn (array $c): array => ['day' => $c['day'], 'period_id' => $c['period_id'], 'reason' => $c['reason']], array_slice($candidates, 0, $limit));
    }

    /**
     * Rooms free at the lesson's slot that hold its students (and support practical work when it is practical),
     * closest capacity first.
     *
     * @param  array<string, mixed>  $lesson
     * @return list<array{room_id: int, capacity: int|null}>
     */
    public function freeRooms(array $lesson, int $students, bool $practical, int $limit = 3): array
    {
        $out = [];
        foreach ($this->board->rooms as $room) {
            if ($room['id'] === ($lesson['room_id'] ?? null) || ($practical && $room['room_type'] !== 2)) {
                continue;
            }
            if ($room['capacity'] !== null && $room['capacity'] < $students) {
                continue;
            }
            if ($this->isBusy('R', $room['id'], $lesson['day_of_week'], $lesson['period_id']) || isset($this->off['R'.$room['id'].':'.$lesson['day_of_week'].':'.$lesson['period_id']])) {
                continue;
            }
            $out[] = ['room_id' => $room['id'], 'capacity' => $room['capacity']];
        }
        usort($out, static fn (array $a, array $b): int => [($a['capacity'] ?? 999) - $students, $a['room_id']] <=> [($b['capacity'] ?? 999) - $students, $b['room_id']]);

        return array_slice($out, 0, $limit);
    }

    /**
     * Teachers who may take some of an overbooked teacher's lessons: qualified for the subject, active, and with
     * room in their week for it. Each proposal names the section, the subject and the loads before / after.
     *
     * @return list<array{section_id: int, subject_id: int, teacher_id: int, weekly: int, load: int, capacity: int}>
     */
    public function relievingTeachers(int $teacherId, int $limit = 3): array
    {
        $active = array_flip($this->board->activeTeacherIds);
        $proposals = [];
        foreach ($this->board->requirements as $r) {
            if ($r['teacher_id'] !== $teacherId || $r['weekly'] === null) {
                continue;
            }
            foreach (array_keys($this->board->teacherSubjects) as $key) {
                [$candidate, $subjectId] = array_map('intval', explode(':', $key));
                if ($candidate === $teacherId || $subjectId !== $r['subject_id'] || ! isset($active[$candidate])) {
                    continue;
                }
                $load = $this->teacherLoad[$candidate] ?? 0;
                $capacity = $this->board->weeklyCapacity($candidate);
                if ($load + $r['weekly'] <= $capacity) {
                    $proposals[] = ['section_id' => $r['section_id'], 'subject_id' => $r['subject_id'], 'teacher_id' => $candidate,
                        'weekly' => $r['weekly'], 'load' => $load, 'capacity' => $capacity];
                }
            }
        }
        usort($proposals, static fn (array $a, array $b): int => [$a['load'] / max(1, $a['capacity']), $a['teacher_id']] <=> [$b['load'] / max(1, $b['capacity']), $b['teacher_id']]);

        return array_slice($proposals, 0, $limit);
    }

    /**
     * The break that, shortened to the double changeover, gives the day a pair of consecutive lessons for
     * practical doubles: the shortest break between two lessons.
     *
     * @return array{period_id: int, minutes: int, to: int}|null
     */
    public function breakToShorten(): ?array
    {
        $ordered = $this->ordered();
        $best = null;
        for ($i = 1, $n = count($ordered) - 1; $i < $n; $i++) {
            $break = $ordered[$i];
            if ($break->periodType !== PeriodType::Break->value
                || $ordered[$i - 1]->periodType !== PeriodType::Lesson->value || $ordered[$i + 1]->periodType !== PeriodType::Lesson->value) {
                continue;
            }
            $minutes = self::length($break);
            if ($best === null || $minutes < $best['minutes']) {
                $best = ['period_id' => $break->id, 'minutes' => $minutes, 'to' => $this->board->settings->doubleChangeoverMinutes];
            }
        }

        return $best;
    }

    /** Students sitting the lesson: its group, else its section (0 when unknown). */
    public function studentsOf(array $lesson): int
    {
        $groupId = $lesson['group_id'] ?? null;
        if ($groupId !== null && isset($this->board->groups[$groupId]['student_count'])) {
            return (int) $this->board->groups[$groupId]['student_count'];
        }

        return (int) ($this->board->sectionInfo[$lesson['section_id']]['students'] ?? 0);
    }

    /** @return list<PeriodSnapshot> the school day in time order */
    public function ordered(): array
    {
        $periods = $this->board->periods;
        usort($periods, static fn (PeriodSnapshot $a, PeriodSnapshot $b): int => [PeriodTimeGuard::minutes($a->startTime), $a->periodNumber] <=> [PeriodTimeGuard::minutes($b->startTime), $b->periodNumber]);

        return $periods;
    }

    public static function length(PeriodSnapshot $p): int
    {
        return (int) PeriodTimeGuard::minutes($p->endTime) - (int) PeriodTimeGuard::minutes($p->startTime);
    }

    /** @param  array<string, mixed>  $lesson */
    private function lessonFits(array $lesson, int $day, int $periodId): bool
    {
        $teachers = TimetableBoard::busyTeachers($lesson);
        foreach ($teachers as $t) {
            if ($this->isBusy('T', $t, $day, $periodId) || isset($this->off['T'.$t.':'.$day.':'.$periodId])) {
                return false;
            }
        }
        if ($this->isBusy('S', $lesson['section_id'], $day, $periodId) || isset($this->off['S'.$lesson['section_id'].':'.$day.':'.$periodId])) {
            return false;
        }
        $room = $lesson['room_id'] ?? null;

        return $room === null || (! $this->isBusy('R', $room, $day, $periodId) && ! isset($this->off['R'.$room.':'.$day.':'.$periodId]));
    }

    private function isBusy(string $prefix, int $id, int $day, int $periodId): bool
    {
        return isset($this->busy[$prefix.$id.':'.$day.':'.$periodId]);
    }
}
