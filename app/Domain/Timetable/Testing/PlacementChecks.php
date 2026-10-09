<?php

namespace App\Domain\Timetable\Testing;

use App\Domain\Timetable\Data\TimetableBoard;

/**
 * Rooms and capacity on the current grid: a room taken twice in one slot, a room smaller than the students it
 * holds, a practical lesson in a room that does not support practical work, practical lessons without any
 * practical room in the school, a section holding more students than its capacity.
 */
final class PlacementChecks
{
    /**
     * @param  array<int, int|null>  $sectionCapacity  section id → capacity (from «الصفوف والشعب»)
     * @return list<array<string, mixed>>
     */
    public function run(TimetableBoard $board, RemedyFinder $remedies, array $sectionCapacity): array
    {
        return [
            ...$this->roomClashes($board, $remedies),
            ...$this->roomFit($board, $remedies),
            ...$this->practicalRooms($board),
            ...$this->sectionCapacity($board, $sectionCapacity),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function roomClashes(TimetableBoard $board, RemedyFinder $remedies): array
    {
        $bySlot = [];
        foreach ($board->schedules as $s) {
            if (($s['room_id'] ?? null) !== null && ($s['joined_to'] ?? null) === null) {
                $bySlot[$s['room_id'].':'.$s['day_of_week'].':'.$s['period_id']][] = $s;
            }
        }
        $issues = [];
        foreach ($bySlot as $group) {
            $clash = [];
            foreach ($group as $i => $a) {
                foreach (array_slice($group, $i + 1) as $b) {
                    if (TimetableBoard::weeksMeet($a['week_no'] ?? null, $b['week_no'] ?? null)) {
                        $clash[$a['id']] = $a;
                        $clash[$b['id']] = $b;
                    }
                }
            }
            if ($clash === []) {
                continue;
            }
            $moving = end($clash);
            $students = $remedies->studentsOf($moving);
            $fixes = array_map(static fn (array $r): array => TestIssue::fix('change_room', 'patch_schedule', ['schedule_id' => $moving['id'], 'room_id' => $r['room_id']], true, ['capacity' => $r['capacity']]),
                $remedies->freeRooms($moving, $students, $board->isPractical($moving['subject_id']), 2));
            foreach ($remedies->freeSlots($moving, 2) as $slot) {
                $fixes[] = TestIssue::fix('move_lesson', 'patch_schedule', ['schedule_id' => $moving['id'], 'day_of_week' => $slot['day'], 'period_id' => $slot['period_id']], true, ['reason' => $slot['reason']]);
            }
            $issues[] = TestIssue::make('grid', TestIssue::ERROR, 'rooms', 'room_double_booked', [
                'room_id' => $moving['room_id'], 'day' => $moving['day_of_week'], 'period_id' => $moving['period_id'],
                'section_id' => $moving['section_id'], 'schedule_ids' => array_keys($clash),
            ], 'room_shared_slot', $fixes, ['clear_room', 'regenerate_repair']);
        }

        return $issues;
    }

    /** @return list<array<string, mixed>> */
    private function roomFit(TimetableBoard $board, RemedyFinder $remedies): array
    {
        $issues = [];
        foreach ($board->schedules as $s) {
            $roomId = $s['room_id'] ?? null;
            if ($roomId === null || ($s['joined_to'] ?? null) !== null || ! isset($board->rooms[$roomId])) {
                continue;
            }
            $room = $board->rooms[$roomId];
            $students = $remedies->studentsOf($s);
            $practical = $board->isPractical($s['subject_id']);
            $at = ['room_id' => $roomId, 'section_id' => $s['section_id'], 'subject_id' => $s['subject_id'], 'day' => $s['day_of_week'], 'period_id' => $s['period_id'], 'schedule_ids' => [$s['id']]];
            if ($room['capacity'] !== null && $students > $room['capacity']) {
                $issues[] = TestIssue::make('grid', TestIssue::ERROR, 'rooms', 'room_capacity_exceeded', $at + ['count' => $students, 'limit' => $room['capacity']],
                    'room_too_small', self::roomFixes($remedies, $s, $students, $practical), ['split_section', 'clear_room']);
            } elseif ($practical && $room['room_type'] !== 2) {
                $issues[] = TestIssue::make('grid', TestIssue::WARNING, 'rooms', 'practical_in_classroom', $at,
                    'room_not_practical', self::roomFixes($remedies, $s, $students, true), ['mark_room_practical']);
            }
        }

        return $issues;
    }

    /** @return list<array<string, mixed>> */
    private function practicalRooms(TimetableBoard $board): array
    {
        if ($board->rooms === [] || array_filter($board->rooms, static fn (array $r): bool => $r['room_type'] === 2) !== []) {
            return [];
        }
        $practical = array_filter($board->requirements, static fn (array $r): bool => in_array($r['subject_id'], $board->practicalSubjectIds, true));
        if ($practical === []) {
            return [];
        }

        return [TestIssue::make('input', TestIssue::WARNING, 'rooms', 'no_practical_rooms', ['count' => count($practical)], 'practical_needs_lab',
            [TestIssue::fix('open_rooms', 'open', ['page' => 'rooms'], true)], ['mark_room_practical'])];
    }

    /**
     * @param  array<int, int|null>  $sectionCapacity
     * @return list<array<string, mixed>>
     */
    private function sectionCapacity(TimetableBoard $board, array $sectionCapacity): array
    {
        $issues = [];
        foreach ($board->sectionInfo as $sectionId => $info) {
            $capacity = $sectionCapacity[$sectionId] ?? null;
            if ($capacity !== null && $info['students'] > $capacity) {
                $issues[] = TestIssue::make('input', TestIssue::ERROR, 'sections', 'section_over_capacity', ['section_id' => $sectionId, 'count' => $info['students'], 'limit' => $capacity],
                    'more_students_than_capacity', [TestIssue::fix('open_sections', 'open', ['page' => 'classes-sections'], true)], ['split_section', 'raise_capacity']);
            }
        }

        return $issues;
    }

    /** @return list<array<string, mixed>> */
    private static function roomFixes(RemedyFinder $remedies, array $lesson, int $students, bool $practical): array
    {
        $fixes = array_map(static fn (array $r): array => TestIssue::fix('change_room', 'patch_schedule', ['schedule_id' => $lesson['id'], 'room_id' => $r['room_id']], true, ['capacity' => $r['capacity']]),
            $remedies->freeRooms($lesson, $students, $practical, 3));
        $fixes[] = TestIssue::fix('clear_room', 'patch_schedule', ['schedule_id' => $lesson['id'], 'room_id' => null], true);

        return $fixes;
    }
}
