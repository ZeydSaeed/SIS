<?php

namespace App\Domain\Timetable\Services;

use App\Domain\Timetable\Data\TimetableBoard;
use App\Domain\Timetable\ValueObjects\AvailabilityKind;

/**
 * «بديل المعلم» (spec §36): who can cover one lesson on one date.
 *
 * A candidate must be active, not the absent teacher, free in the slot (no lesson of their own that weekday
 * and period, not already covering something then) and not unavailable. Compatibility (0–100):
 *   +45 assigned the subject (teacher_subjects) · +15 already teaches this section · +10 not marked «avoid»
 *   +30 × (1 − lessons that day ÷ daily limit)  — a lighter day ranks higher.
 * Over the daily limit counts against, not out: the school decides.
 */
final class SubstituteRanker
{
    /**
     * @param  array{id: int, section_id: int, day_of_week: int, period_id: int, subject_id: int, teacher_id: int}  $lesson
     * @param  list<int>  $busyOnDate  teachers already covering a lesson in that slot on that date
     * @param  array<int, string>  $names  teacher id → name
     * @return list<array{teacher_id: int, name: string, compatibility: int, qualified: bool, knows_section: bool, lessons_that_day: int, over_limit: bool}>
     */
    public function rank(TimetableBoard $board, array $lesson, array $busyOnDate = [], array $names = [], int $limit = 10): array
    {
        $day = $lesson['day_of_week'];
        $period = $lesson['period_id'];
        $busy = array_flip($busyOnDate);
        $dayLoad = [];
        $knows = [];
        foreach ($board->schedules as $s) {
            foreach (TimetableBoard::busyTeachers($s) as $t) {
                if ($s['day_of_week'] === $day) {
                    $dayLoad[$t] = ($dayLoad[$t] ?? 0) + 1;
                    if ($s['period_id'] === $period) {
                        $busy[$t] = true;
                    }
                }
                if ($s['section_id'] === $lesson['section_id']) {
                    $knows[$t] = true;
                }
            }
        }
        $unavailable = [];
        $avoid = [];
        foreach ($board->availability as $a) {
            if ($a['teacher_id'] !== null && $a['day'] === $day && $a['period_id'] === $period) {
                if ($a['kind'] === AvailabilityKind::Unavailable->value) {
                    $unavailable[$a['teacher_id']] = true;
                } elseif ($a['kind'] === AvailabilityKind::Avoid->value) {
                    $avoid[$a['teacher_id']] = true;
                }
            }
        }

        $max = max(1, $board->settings->maxTeacherPerDay);
        $ranked = [];
        foreach ($board->activeTeacherIds as $t) {
            if ($t === $lesson['teacher_id'] || isset($busy[$t]) || isset($unavailable[$t])) {
                continue;
            }
            $qualified = isset($board->teacherSubjects[$t.':'.$lesson['subject_id']]);
            $load = $dayLoad[$t] ?? 0;
            $score = ($qualified ? 45 : 0) + (isset($knows[$t]) ? 15 : 0) + (isset($avoid[$t]) ? 0 : 10)
                + (int) round(30 * max(0.0, 1 - $load / $max));
            $ranked[] = [
                'teacher_id' => $t,
                'name' => $names[$t] ?? '#'.$t,
                'compatibility' => min(100, $score),
                'qualified' => $qualified,
                'knows_section' => isset($knows[$t]),
                'lessons_that_day' => $load,
                'over_limit' => $load + 1 > $max,
            ];
        }
        usort($ranked, static fn (array $a, array $b): int => [$b['compatibility'], $a['lessons_that_day'], $a['teacher_id']] <=> [$a['compatibility'], $b['lessons_that_day'], $b['teacher_id']]);

        return array_slice($ranked, 0, $limit);
    }
}
