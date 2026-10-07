<?php

namespace App\Application\Timetable\Queries;

use App\Application\Timetable\DTOs\TimetableInsightDTO;
use App\Domain\Timetable\Repositories\GenerationRunRepositoryInterface;
use App\Domain\Timetable\Services\TimetableVersionComparer;

/**
 * «نتيجة التوليد» for review: the run, its unplaced blocks with reasons and suggestions, broken / relaxed rules,
 * audit counts, and how the proposal differs from the grid it started from. The input snapshot stays server-side.
 */
final class GetGenerationRunHandler
{
    public function __construct(
        private readonly GenerationRunRepositoryInterface $runs,
        private readonly TimetableVersionComparer $comparer,
    ) {}

    public function handle(GetGenerationRunQuery $query): ?TimetableInsightDTO
    {
        $run = $this->runs->find($query->schoolId, $query->runId, true);
        if ($run === null) {
            return null;
        }
        $result = $run['result'] ?? [];
        $before = array_values(array_filter($run['input_snapshot']['schedules'] ?? [], static fn (array $s): bool => in_array($s['id'], $result['replaced_ids'] ?? [], true)));
        $diff = $result === [] ? null : $this->comparer->compare($before, $result['rows'] ?? [], 50);
        unset($run['input_snapshot']);
        $run['result'] = $result === [] ? null : [
            'unplaced' => $result['unplaced'] ?? [],
            'violations' => $result['violations'] ?? [],
            'compile_notes' => $result['compile_notes'] ?? [],
            'issues' => array_slice($result['issues'] ?? [], 0, 50),
            'stats' => $result['stats'] ?? [],
            'diff' => $diff,
            'rows' => $result['rows'] ?? [],
        ];

        return new TimetableInsightDTO($run);
    }
}
