<?php

namespace App\Application\Timetable\Support;

use App\Domain\Timetable\Data\TimetableBoard;
use App\Domain\Timetable\Solver\SolverCard;
use App\Domain\Timetable\Solver\SolverProblem;

/**
 * Bridges grid lessons and solver blocks for «اقتراح أماكن»: which block a lesson belongs to, why it cannot be
 * suggested, and the solver's options turned back into grid terms (day, period id, the lesson to swap with).
 */
final class MoveSuggestionMapper
{
    /** @return array<string, mixed>|null the lesson on the board */
    public function lesson(TimetableBoard $board, int $scheduleId): ?array
    {
        foreach ($board->schedules as $s) {
            if ($s['id'] === $scheduleId) {
                return $s;
            }
        }

        return null;
    }

    /** 'locked' / 'joined' when the lesson cannot be moved by suggestion, else null. */
    public function refusal(array $lesson): ?string
    {
        if ($lesson['locked'] ?? false) {
            return 'locked';
        }

        return ($lesson['joined_to'] ?? null) !== null ? 'joined' : null;
    }

    /** The optimize-mode block holding the lesson (its starting position covers the lesson's slot). */
    public function cardOf(SolverProblem $problem, array $lesson): ?SolverCard
    {
        $index = array_search($lesson['period_id'], $problem->periodIds, true);
        if ($index === false) {
            return null;
        }
        $target = [$lesson['section_id'], $lesson['group_id'] ?? null];
        foreach ($problem->cards as $card) {
            $at = $card->initial;
            $covers = $at !== null && $at[0] === $lesson['day_of_week'] && $index >= $at[1] && $index < $at[1] + $card->length;
            if ($covers && $card->subjectId === $lesson['subject_id'] && $card->leadTeacherId === $lesson['teacher_id'] && in_array($target, $card->targets, true)) {
                return $card;
            }
        }

        return null;
    }

    /**
     * @param  list<array{kind: string, day: int, index: int, facility: string|null, with: string|null, hard: int, soft: int}>  $options
     * @return list<array{kind: string, day: int, period_id: int, with_schedule_id: int|null, hard: int, soft: int}>
     */
    public function toGrid(TimetableBoard $board, SolverProblem $problem, array $options): array
    {
        $out = [];
        foreach ($options as $o) {
            $periodId = $problem->periodIds[$o['index']];
            $with = $o['with'] === null ? null : $this->scheduleAt($board, $problem->cards[$o['with']], $o['day'], $periodId);
            if ($o['with'] !== null && $with === null) {
                continue;
            }
            $out[] = ['kind' => $o['kind'], 'day' => $o['day'], 'period_id' => $periodId, 'with_schedule_id' => $with, 'hard' => $o['hard'], 'soft' => $o['soft']];
        }

        return $out;
    }

    private function scheduleAt(TimetableBoard $board, SolverCard $card, int $day, int $periodId): ?int
    {
        [$section, $group] = $card->targets[0];
        foreach ($board->schedules as $s) {
            if ($s['section_id'] === $section && ($s['group_id'] ?? null) === $group && $s['day_of_week'] === $day
                && $s['period_id'] === $periodId && $s['subject_id'] === $card->subjectId) {
                return $s['id'];
            }
        }

        return null;
    }
}
