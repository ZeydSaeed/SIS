<?php

namespace App\Domain\Timetable\Testing;

/**
 * Data the printed timetable relies on: two teachers / subjects / rooms that would print the same abbreviation,
 * and entities without an abbreviation (the suggestion is printed until one is chosen — one click stores them,
 * through each entity's own page).
 */
final class DataChecks
{
    private const TARGETS = ['teachers' => 'teacher', 'subjects' => 'subject', 'rooms' => 'room'];

    /**
     * @param  array<string, array<int|string, array<string, mixed>>>  $display  the display catalogue (by kind, by id)
     * @param  array<string, array<int, true>>  $used  ids the timetable shows, by kind
     * @return list<array<string, mixed>>
     */
    public function run(array $display, array $used): array
    {
        $issues = [];
        foreach (self::TARGETS as $kind => $target) {
            $rows = array_values(array_filter($display[$kind] ?? [], static fn (array $r): bool => isset($used[$kind][(int) $r['id']])));
            $byShort = [];
            $missing = [];
            foreach ($rows as $row) {
                $byShort[mb_strtolower((string) $row['short'])][] = $row;
                if ($row['abbreviation'] === null && ($row['suggested'] ?? null) !== null) {
                    $missing[] = $row;
                }
            }
            foreach ($byShort as $short => $same) {
                if (count($same) > 1) {
                    $issues[] = TestIssue::make('input', TestIssue::WARNING, 'data', 'duplicate_abbreviation', [
                        'count' => count($same), 'detail' => $target.':'.$short.':'.implode(',', array_map(static fn (array $r): int => (int) $r['id'], $same)),
                    ], 'same_short_label', [TestIssue::fix('edit_appearance', 'appearance', ['target' => $target, 'ids' => array_map(static fn (array $r): int => (int) $r['id'], $same)], true)]);
                }
            }
            if ($missing !== []) {
                $issues[] = TestIssue::make('input', TestIssue::SUGGESTION, 'data', 'abbreviation_missing', ['count' => count($missing), 'detail' => $target], 'suggested_abbreviation_printed', [
                    TestIssue::fix('apply_suggested_abbreviations', 'apply_abbreviations', ['target' => $target, 'items' => array_map(
                        static fn (array $r): array => ['id' => (int) $r['id'], 'abbreviation' => $r['suggested']],
                        array_slice($missing, 0, 200),
                    )], true),
                ]);
            }
        }

        return $issues;
    }
}
