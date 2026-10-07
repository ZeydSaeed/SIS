<?php

namespace App\Domain\Timetable\Solver;

/**
 * Turns placed blocks into grid rows (one per target section · lesson period). The first target of a block
 * is the lead row (teacher / room occupancy); the other targets' rows point at it (`lead_key`) — joined classes.
 */
final class PlacementRows
{
    /**
     * @return list<array{lead_key: string, is_lead: bool, card_id: string, section_id: int, group_id: int|null, day_of_week: int, period_id: int, week_no: int|null, subject_id: int, teacher_id: int, co_teacher_id: int|null, room_id: int|null, activity_id: int|null}>
     */
    public static function from(SolverProblem $problem, SolverResult $result): array
    {
        $rows = [];
        foreach ($result->placements as $cardId => [$day, $start, $facility]) {
            $card = $problem->cards[$cardId];
            for ($k = $start; $k < $start + $card->length; $k++) {
                foreach ($card->targets as $n => [$sectionId, $groupId]) {
                    $rows[] = [
                        'lead_key' => $cardId.'@'.$k,
                        'is_lead' => $n === 0,
                        'card_id' => $cardId,
                        'section_id' => $sectionId,
                        'group_id' => $groupId,
                        'day_of_week' => $day,
                        'period_id' => $problem->periodIds[$k],
                        'week_no' => $card->weekNo,
                        'subject_id' => $card->subjectId,
                        'teacher_id' => $card->leadTeacherId,
                        'co_teacher_id' => $card->coTeacherId,
                        'room_id' => $facility === null ? null : ($card->rooms[$facility] ?? null),
                        'activity_id' => $card->activityId,
                    ];
                }
            }
        }

        return $rows;
    }
}
