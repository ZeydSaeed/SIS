<?php

namespace App\Domain\Timetable\Services;

use App\Domain\Timetable\Data\TimetableBoard;
use App\Domain\Timetable\Solver\ConstraintCompiler;
use App\Domain\Timetable\ValueObjects\GenerationMode;

/**
 * «جاهزية الجدول»: checks the input before anything is placed — can these lessons fit this week at all?
 *
 * blocker — no timetable can satisfy it as entered (no lesson periods, a section or a teacher with more
 *           weekly lessons than the week can hold, a subject above its daily limit × days, a practical with
 *           no double slot in the day, a lesson whose teacher is inactive or not assigned the subject);
 * warning — the data is incomplete or ambiguous (no weekly load in the curriculum, one subject of a section
 *           split between several teachers — each one is counted for the full weekly load);
 * info    — worth a look (a section without lessons, a teacher filled to 90 % or more of the week).
 *
 * Verdict: `blocked` while any blocker stands, else `ready`. Readiness: the share of each input that is
 * complete, and their mean. Nothing is stored — the advice is computed on read from the board.
 */
final class TimetableAdvisor
{
    public const READY = 'ready';

    public const BLOCKED = 'blocked';

    /** A teacher whose weekly load reaches this share of the week is flagged as tight. */
    private const TIGHT_SHARE = 0.9;

    private const SEVERITY_ORDER = ['blocker' => 0, 'warning' => 1, 'info' => 2];

    /**
     * @return array{
     *     verdict: string,
     *     findings: list<array{severity: string, code: string, section_id: int|null, teacher_id: int|null, subject_id: int|null, count: int|null, limit: int|null}>,
     *     readiness: array{overall: int, school_day: int, weekly_loads: int|null, qualified: int|null, sections: int|null, capacity: int|null},
     *     totals: array{sections: int, teachers: int, requirements: int, weekly_lessons: int, lesson_periods: int, slots_per_week: int}
     * }
     */
    public function advise(TimetableBoard $board): array
    {
        $days = count($board->settings->days);
        $slots = $days * count($board->lessonPeriodIds);
        $findings = [];

        if ($board->lessonPeriodIds === []) {
            $findings[] = self::finding('blocker', 'no_lesson_periods');
        }

        $doubleSlot = $this->hasDoubleSlot($board);
        $practicalsSeen = [];
        $activeTeachers = array_flip($board->activeTeacherIds);
        $qualified = 0;
        $withLoad = 0;
        $sectionLoad = [];
        $teacherLoad = [];
        $subjectLoad = [];
        $subjectTeachers = [];

        foreach ($board->requirements as $r) {
            $teacherOk = isset($activeTeachers[$r['teacher_id']]) && isset($board->teacherSubjects[$r['teacher_id'].':'.$r['subject_id']]);
            if ($teacherOk) {
                $qualified++;
            } elseif (! isset($activeTeachers[$r['teacher_id']])) {
                $findings[] = self::finding('blocker', 'teacher_inactive', $r);
            } else {
                $findings[] = self::finding('blocker', 'teacher_not_qualified', $r);
            }

            $subjectTeachers[$r['section_id'].':'.$r['subject_id']][$r['teacher_id']] = true;

            if ($r['weekly'] === null) {
                $findings[] = self::finding('warning', 'weekly_load_missing', $r);

                continue;
            }
            $withLoad++;
            $sectionLoad[$r['section_id']] = ($sectionLoad[$r['section_id']] ?? 0) + $r['weekly'];
            $teacherLoad[$r['teacher_id']] = ($teacherLoad[$r['teacher_id']] ?? 0) + $r['weekly'];
            $key = $r['section_id'].':'.$r['subject_id'];
            $subjectLoad[$key] = ($subjectLoad[$key] ?? ['section_id' => $r['section_id'], 'subject_id' => $r['subject_id'], 'weekly' => 0]);
            $subjectLoad[$key]['weekly'] += $r['weekly'];

            if ($board->isPractical($r['subject_id']) && $r['weekly'] >= 2 && ! $doubleSlot && ! isset($practicalsSeen[$r['subject_id']]) && $board->lessonPeriodIds !== []) {
                $practicalsSeen[$r['subject_id']] = true;
                $findings[] = self::finding('blocker', 'practical_no_double_slot', ['subject_id' => $r['subject_id']]);
            }
        }

        foreach ($subjectTeachers as $key => $teachers) {
            if (count($teachers) > 1) {
                [$sectionId, $subjectId] = array_map('intval', explode(':', $key));
                $findings[] = self::finding('warning', 'subject_split_between_teachers', ['section_id' => $sectionId, 'subject_id' => $subjectId], count($teachers));
            }
        }

        $sectionsOver = 0;
        foreach ($sectionLoad as $sectionId => $load) {
            if ($slots > 0 && $load > $slots) {
                $sectionsOver++;
                $findings[] = self::finding('blocker', 'section_overbooked', ['section_id' => $sectionId], $load, $slots);
            }
        }

        $teacherCapacity = min($slots, $days * $board->settings->maxTeacherPerDay);
        $teachersOver = 0;
        foreach ($teacherLoad as $teacherId => $load) {
            if ($slots > 0 && $load > $teacherCapacity) {
                $teachersOver++;
                $findings[] = self::finding('blocker', 'teacher_overbooked', ['teacher_id' => $teacherId], $load, $teacherCapacity);
            } elseif ($teacherCapacity > 0 && $load >= self::TIGHT_SHARE * $teacherCapacity) {
                $findings[] = self::finding('info', 'teacher_tight', ['teacher_id' => $teacherId], $load, $teacherCapacity);
            }
        }

        $subjectCapacity = $days * $board->settings->maxSubjectPerDay;
        foreach ($subjectLoad as $s) {
            if ($s['weekly'] > $subjectCapacity) {
                $findings[] = self::finding('blocker', 'subject_over_daily_limit', $s, $s['weekly'], $subjectCapacity);
            }
        }

        array_push($findings, ...$this->engineFindings($board));

        $covered = array_flip([...array_column($board->requirements, 'section_id'), ...array_merge([], ...array_map(
            static fn (array $a): array => array_column($a['targets'], 'section_id'),
            $board->activities,
        ))]);
        foreach ($board->sectionIds as $sectionId) {
            if (! isset($covered[$sectionId])) {
                $findings[] = self::finding('info', 'section_without_lessons', ['section_id' => $sectionId]);
            }
        }

        usort($findings, static fn (array $a, array $b): int => [self::SEVERITY_ORDER[$a['severity']], $a['code'], $a['section_id'] ?? 0, $a['teacher_id'] ?? 0, $a['subject_id'] ?? 0]
            <=> [self::SEVERITY_ORDER[$b['severity']], $b['code'], $b['section_id'] ?? 0, $b['teacher_id'] ?? 0, $b['subject_id'] ?? 0]);

        $requirements = count($board->requirements);
        $sectionCount = $board->sectionIds !== [] ? count($board->sectionIds) : count($covered);
        $coveredSections = count(array_intersect_key(array_flip($board->sectionIds), $covered));
        $loadedEntities = count($sectionLoad) + count($teacherLoad);
        $readiness = [
            'school_day' => $board->lessonPeriodIds === [] || $practicalsSeen !== [] ? 0 : 100,
            'weekly_loads' => self::percent($withLoad, $requirements),
            'qualified' => self::percent($qualified, $requirements),
            // Unknown when the board was built without the section list.
            'sections' => $board->sectionIds === [] ? null : self::percent($coveredSections, $sectionCount),
            'capacity' => self::percent($loadedEntities - $sectionsOver - $teachersOver, $loadedEntities),
        ];
        $measured = array_filter($readiness, static fn (?int $v): bool => $v !== null);

        return [
            'verdict' => array_filter($findings, static fn (array $f): bool => $f['severity'] === 'blocker') === [] ? self::READY : self::BLOCKED,
            'findings' => $findings,
            'readiness' => ['overall' => (int) round(array_sum($measured) / count($measured))] + $readiness,
            'totals' => [
                'sections' => $sectionCount,
                'teachers' => count(array_unique(array_column($board->requirements, 'teacher_id'))),
                'requirements' => $requirements,
                'weekly_lessons' => array_sum($sectionLoad),
                'lesson_periods' => count($board->lessonPeriodIds),
                'slots_per_week' => $slots,
            ],
        ];
    }

    /**
     * What the engine configuration adds (activities, rooms, workshops, time-off), read from the compiled
     * problem: blocks that can never be placed (`activity_blocked`, detail = reason), a room / workshop
     * asked for more periods than it is open (`facility_overbooked`), a teacher whose time-off leaves fewer
     * slots than lessons (`teacher_time_off_overbooked`).
     *
     * @return list<array<string, mixed>>
     */
    private function engineFindings(TimetableBoard $board): array
    {
        if ($board->activities === [] && $board->availability === [] || $board->lessonPeriodIds === []) {
            return [];
        }
        $problem = (new ConstraintCompiler)->compile($board, GenerationMode::Balanced);
        $findings = [];
        $seen = [];
        foreach ($problem->compileNotes as $note) {
            $card = $problem->cards[$note['card_id']] ?? null;
            $key = ($card?->activityKey ?? $note['card_id']).':'.$note['reason'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $findings[] = self::finding('blocker', 'activity_blocked', [
                'section_id' => $card?->targets[0][0],
                'teacher_id' => $card?->leadTeacherId,
                'subject_id' => $card?->subjectId,
            ], detail: $note['reason']);
        }

        $open = count($problem->days) * $problem->periodCount() * $problem->weeks;
        $teacherDemand = [];
        $facilityDemand = [];
        foreach ($problem->cards as $card) {
            if ($card->blockedReason !== null) {
                continue;
            }
            $periods = $card->length * count($card->weeks);
            foreach ($card->teachers as $t) {
                $teacherDemand[$t] = ($teacherDemand[$t] ?? 0) + $periods;
            }
            if (count($card->facilities) === 1) {
                $facilityDemand[$card->facilities[0]] = ($facilityDemand[$card->facilities[0]] ?? 0) + $periods;
            }
        }
        foreach ($problem->fixed as $item) {
            foreach ($item['teachers'] as $t) {
                $teacherDemand[$t] = ($teacherDemand[$t] ?? 0) + count($item['weeks']);
            }
        }
        foreach ($teacherDemand as $t => $demand) {
            $off = count($problem->unavailable['T'.$t] ?? []);
            if ($off > 0 && $demand > $open - $off) {
                $findings[] = self::finding('blocker', 'teacher_time_off_overbooked', ['teacher_id' => $t], $demand, $open - $off);
            }
        }
        foreach ($facilityDemand as $facility => $demand) {
            $available = $open - count($problem->unavailable[$facility] ?? []);
            if ($demand > $available) {
                $findings[] = self::finding('blocker', 'facility_overbooked', [], $demand, $available, $facility);
            }
        }

        return $findings;
    }

    /** Some two lesson periods of the day form a practical double (no main break between them). */
    private function hasDoubleSlot(TimetableBoard $board): bool
    {
        $ids = $board->lessonPeriodIds;
        for ($i = 1, $n = count($ids); $i < $n; $i++) {
            if ($board->adjacent($ids[$i - 1], $ids[$i])) {
                return true;
            }
        }

        return false;
    }

    private static function percent(int $part, int $whole): ?int
    {
        return $whole === 0 ? null : (int) floor(100 * $part / $whole);
    }

    /**
     * @param  array{section_id?: int|null, teacher_id?: int|null, subject_id?: int|null}  $at
     * @return array{severity: string, code: string, section_id: int|null, teacher_id: int|null, subject_id: int|null, count: int|null, limit: int|null, detail: string|null}
     */
    private static function finding(string $severity, string $code, array $at = [], ?int $count = null, ?int $limit = null, ?string $detail = null): array
    {
        return [
            'severity' => $severity,
            'code' => $code,
            'section_id' => $at['section_id'] ?? null,
            'teacher_id' => $at['teacher_id'] ?? null,
            'subject_id' => $at['subject_id'] ?? null,
            'count' => $count,
            'limit' => $limit,
            'detail' => $detail,
        ];
    }
}
