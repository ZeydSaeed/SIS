<?php

namespace App\Domain\Results\Services;

/**
 * Pre-finalize / pre-publish reconciliation of a results sheet (EXAMIS A2). Pure and deterministic: it only classifies
 * the rows the sheet already shows, so the numbers always agree with the table. An issue lists the enrollments it
 * concerns; `ready` says whether every student of the scope is in a publishable state.
 *
 * @phpstan-type Issue array{code: string, count: int, enrollment_ids: list<int>}
 * @phpstan-type Report array{students: int, issues: list<Issue>, ready: array<string, bool>}
 */
final class ResultsReconciliation
{
    /**
     * @param  list<array{
     *     enrollment_id: int, seats: int, current_grades: int, finalized_grades: int,
     *     operational: array{version: int, weighted_total: ?string, incomplete: bool}|null,
     *     official: array{version: int, weighted_total: ?string, incomplete: bool}|null
     * }>  $rows
     * @return Report
     */
    public static function forTerm(array $rows): array
    {
        $issues = new IssueBag;
        foreach ($rows as $row) {
            $id = $row['enrollment_id'];
            $official = $row['official'];
            $operational = $row['operational'];
            if ($row['current_grades'] === 0) {
                $issues->add('not_graded', $id);
            } elseif ($row['finalized_grades'] < $row['current_grades'] && $official === null) {
                $issues->add('grades_not_finalized', $id);
            }
            if ($official === null && $operational === null) {
                $issues->add('no_result', $id);
            } elseif ($official === null) {
                $issues->add('not_finalized', $id);
            } elseif ($official['incomplete']) {
                $issues->add('official_incomplete', $id);
            }
            if ($official !== null && $operational !== null && $official['weighted_total'] !== $operational['weighted_total']) {
                $issues->add('operational_differs', $id);
            }
        }
        $blocking = $issues->occurred(['no_result', 'not_finalized', 'official_incomplete']);

        return ['students' => count($rows), 'issues' => $issues->all(), 'ready' => ['term' => $rows !== [] && $blocking === []]];
    }

    /**
     * @param  list<array{
     *     enrollment_id: int, term_results: array{operational: int, official: int},
     *     annual: array{operational: array{version: int, value: ?string, incomplete: bool}|null, official: array{version: int, value: ?string, incomplete: bool}|null},
     *     gpa: array{operational: array{version: int, value: ?string, incomplete: bool}|null, official: array{version: int, value: ?string, incomplete: bool}|null},
     *     rank: ?int, transcript: array{number: string, version: int, up_to_date: bool}|null
     * }>  $rows
     * @return Report
     */
    public static function forYear(array $rows, bool $hasRanking): array
    {
        $issues = new IssueBag;
        foreach ($rows as $row) {
            $id = $row['enrollment_id'];
            if ($row['term_results']['official'] === 0) {
                $issues->add('no_official_term_results', $id);
            }
            foreach (['annual', 'gpa'] as $kind) {
                $official = $row[$kind]['official'];
                if ($official === null) {
                    $issues->add($kind.'_missing', $id);
                } elseif ($official['incomplete']) {
                    $issues->add($kind.'_incomplete', $id);
                }
                $operational = $row[$kind]['operational'];
                if ($official !== null && $operational !== null && $official['value'] !== $operational['value']) {
                    $issues->add($kind.'_operational_differs', $id);
                }
            }
            if ($row['transcript'] === null) {
                $issues->add('transcript_missing', $id);
            } elseif (! $row['transcript']['up_to_date']) {
                $issues->add('transcript_outdated', $id);
            }
        }
        if ($rows !== [] && ! $hasRanking) {
            $issues->add('ranking_missing', 0);
        }
        $none = static fn (array $codes): bool => $rows !== [] && $issues->occurred($codes) === [];

        return ['students' => count($rows), 'issues' => $issues->all(), 'ready' => [
            'annual' => $none(['annual_missing', 'annual_incomplete']),
            'gpa' => $none(['gpa_missing', 'gpa_incomplete']),
            'transcript' => $none(['transcript_missing', 'transcript_outdated']),
        ]];
    }
}
