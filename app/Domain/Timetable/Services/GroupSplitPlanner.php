<?php

namespace App\Domain\Timetable\Services;

/**
 * «تقسيم الشعبة إلى مجموعات» (spec §44): how many groups and how big, so no group exceeds the capacity
 * (e.g. a workshop's safety capacity): 36 students, capacity 18 → 18 + 18; 40 → 14 + 13 + 13.
 * Members are dealt round-robin in a stable order so groups stay balanced.
 */
final class GroupSplitPlanner
{
    /** Why a split request cannot be honoured (null = fine): a name, and 2–10 groups or a positive capacity. */
    public function error(string $name, ?int $groupCount, ?int $capacity): ?string
    {
        $name = trim($name);
        $countOk = $groupCount === null || ($groupCount >= 2 && $groupCount <= 10);
        $capacityOk = $capacity === null || $capacity >= 1;

        return $name === '' || mb_strlen($name) > 100 || ($groupCount === null && $capacity === null) || ! $countOk || ! $capacityOk
            ? 'timetable.split_invalid'
            : null;
    }

    /**
     * The groups to create: sizes within the capacity (or the count), members dealt in order, names given or 1, 2, 3 ….
     *
     * @param  list<int>  $memberIds
     * @param  list<string>  $names
     * @return list<array{name: string, student_count: int, members: list<int>}>|null null when the split would not give 2–10 groups
     */
    public function plan(array $memberIds, ?int $groupCount, ?int $capacity, array $names = []): ?array
    {
        $sizes = $this->sizes(count($memberIds), $capacity ?? PHP_INT_MAX, $groupCount);
        if (count($sizes) < 2 || count($sizes) > 10) {
            return null;
        }
        $dealt = $this->deal($memberIds, count($sizes));
        $groups = [];
        foreach ($sizes as $i => $size) {
            $label = trim((string) ($names[$i] ?? ''));
            $groups[] = ['name' => mb_substr($label !== '' ? $label : (string) ($i + 1), 0, 100), 'student_count' => $size, 'members' => $dealt[$i]];
        }

        return $groups;
    }

    /** @return list<int> group sizes */
    public function sizes(int $students, int $capacity, ?int $groups = null): array
    {
        $count = max(1, $groups ?? (int) ceil($students / max(1, $capacity)));
        $base = intdiv($students, $count);
        $extra = $students % $count;

        return array_map(static fn (int $i): int => $base + ($i < $extra ? 1 : 0), range(0, $count - 1));
    }

    /**
     * @param  list<int>  $memberIds  stable order (e.g. by name)
     * @return list<list<int>> members per group
     */
    public function deal(array $memberIds, int $groups): array
    {
        $result = array_fill(0, max(1, $groups), []);
        foreach (array_values($memberIds) as $i => $id) {
            $result[$i % count($result)][] = $id;
        }

        return $result;
    }
}
