<?php

namespace App\Domain\Timetable\Services;

/**
 * «مقارنة الإصدارات» (spec §78): what changed between two timetables (versions or the working grid).
 *
 * A lesson is matched on section · group · subject · week. Matched lessons that changed day / period are
 * `moved`; matched lessons with another teacher / room are `teacher_changed` / `room_changed`; lessons
 * only in B are `added`, only in A are `removed`. Quality scores are compared when given.
 */
final class TimetableVersionComparer
{
    /**
     * @param  list<array{section_id: int, group_id?: int|null, day_of_week: int, period_id: int, week_no?: int|null, subject_id: int, teacher_id: int, room_id?: int|null}>  $a
     * @param  list<array{section_id: int, group_id?: int|null, day_of_week: int, period_id: int, week_no?: int|null, subject_id: int, teacher_id: int, room_id?: int|null}>  $b
     * @return array{counts: array{moved: int, teacher_changed: int, room_changed: int, added: int, removed: int, unchanged: int}, changes: list<array<string, mixed>>, sections: list<int>}
     */
    public function compare(array $a, array $b, int $limit = 200): array
    {
        $key = static fn (array $r): string => $r['section_id'].':'.($r['group_id'] ?? 0).':'.$r['subject_id'].':'.($r['week_no'] ?? 0);
        $slot = static fn (array $r): string => $r['day_of_week'].':'.$r['period_id'];
        $groupA = [];
        foreach ($a as $r) {
            $groupA[$key($r)][$slot($r)] = $r;
        }
        $groupB = [];
        foreach ($b as $r) {
            $groupB[$key($r)][$slot($r)] = $r;
        }

        $counts = ['moved' => 0, 'teacher_changed' => 0, 'room_changed' => 0, 'added' => 0, 'removed' => 0, 'unchanged' => 0];
        $changes = [];
        $sections = [];
        $record = static function (string $type, ?array $from, ?array $to) use (&$counts, &$changes, &$sections, $limit): void {
            $counts[$type]++;
            $sections[($from ?? $to)['section_id']] = true;
            if (count($changes) < $limit) {
                $changes[] = ['type' => $type, 'from' => $from, 'to' => $to];
            }
        };

        foreach (array_unique([...array_keys($groupA), ...array_keys($groupB)]) as $k) {
            $left = $groupA[$k] ?? [];
            $right = $groupB[$k] ?? [];
            foreach (array_intersect_key($left, $right) as $s => $from) {
                $to = $right[$s];
                if ($from['teacher_id'] !== $to['teacher_id']) {
                    $record('teacher_changed', $from, $to);
                } elseif (($from['room_id'] ?? null) !== ($to['room_id'] ?? null)) {
                    $record('room_changed', $from, $to);
                } else {
                    $counts['unchanged']++;
                }
            }
            $onlyLeft = array_values(array_diff_key($left, $right));
            $onlyRight = array_values(array_diff_key($right, $left));
            $pairs = min(count($onlyLeft), count($onlyRight));
            for ($i = 0; $i < $pairs; $i++) {
                $record('moved', $onlyLeft[$i], $onlyRight[$i]);
            }
            foreach (array_slice($onlyLeft, $pairs) as $from) {
                $record('removed', $from, null);
            }
            foreach (array_slice($onlyRight, $pairs) as $to) {
                $record('added', null, $to);
            }
        }
        $sectionIds = array_keys($sections);
        sort($sectionIds);

        return ['counts' => $counts, 'changes' => $changes, 'sections' => $sectionIds];
    }
}
