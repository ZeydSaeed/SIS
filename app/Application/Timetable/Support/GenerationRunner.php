<?php

namespace App\Application\Timetable\Support;

use App\Application\Timetable\Commands\ApplyTimetableGenerationHandler;
use App\Domain\Timetable\Data\TimetableBoard;
use App\Domain\Timetable\Repositories\GenerationRunRepositoryInterface;
use App\Domain\Timetable\Services\TimetableAuditor;
use App\Domain\Timetable\Services\TimetableFingerprint;
use App\Domain\Timetable\Services\TimetableQualityScorer;
use App\Domain\Timetable\Solver\ConstraintCompiler;
use App\Domain\Timetable\Solver\PlacementRows;
use App\Domain\Timetable\Solver\SolverOptions;
use App\Domain\Timetable\Solver\SolverProblem;
use App\Domain\Timetable\Solver\SolverResult;
use App\Domain\Timetable\Solver\TimetableSolverInterface;
use App\Domain\Timetable\ValueObjects\GenerationMode;
use App\Domain\Timetable\ValueObjects\GenerationRunStatus;

/**
 * Executes one generation run (spec §61, §65–66):
 * snapshot the projection → compile → solve (progress, cancel) → rows → verify (audit + quality on the
 * resulting grid) → store the result for review. Nothing is written to the grid here; applying is a separate,
 * human step ({@see ApplyTimetableGenerationHandler}).
 */
final class GenerationRunner
{
    public function __construct(
        private readonly GenerationRunRepositoryInterface $runs,
        private readonly TimetableBoardLoader $boards,
        private readonly ConstraintCompiler $compiler,
        private readonly TimetableSolverInterface $solver,
        private readonly TimetableFingerprint $fingerprints,
        private readonly TimetableAuditor $auditor,
        private readonly TimetableQualityScorer $quality,
    ) {}

    /** @return bool false when the run was not queued (cancelled meanwhile) */
    public function execute(int $schoolId, int $runId): bool
    {
        $run = $this->runs->find($schoolId, $runId);
        if ($run === null || $run['status'] !== GenerationRunStatus::Queued->value) {
            return false;
        }
        try {
            $mode = GenerationMode::from($run['mode']);
            $board = $this->boards->load($schoolId, $run['academic_year_id']);
            $board = GenerationScope::whatIf($board, $run['options']['what_if'] ?? []);
            $board = $board->withRules(GenerationScope::objectiveRules($board, $run['options']['objectives'] ?? []));
            $problem = $this->compiler->compile($board, $mode, GenerationScope::resolve($board, $run['scope']));
            if (! $this->runs->markRunning($schoolId, $runId, $this->fingerprints->of($board), self::snapshot($board, $run))) {
                return false;
            }
            $options = new SolverOptions(
                seed: (int) ($run['options']['seed'] ?? 1),
                timeBudgetSeconds: max(2.0, min(120.0, (float) ($run['options']['time_budget'] ?? 20))),
                maxIterations: 200_000,
            );
            $result = $this->solver->solve($problem, $options, new RunProgress($this->runs, $schoolId, $runId));
            $outcome = $this->outcome($board, $problem, $result);
            $cancelled = $result->stopped && $this->runs->cancelRequested($schoolId, $runId);
            $this->runs->finish($schoolId, $runId, ($cancelled ? GenerationRunStatus::Cancelled : GenerationRunStatus::Succeeded)->value, $outcome);
        } catch (\Throwable $e) {
            // The run row carries the failure (status 4 + error) — the page shows it; the queue does not retry.
            $this->runs->fail($schoolId, $runId, $e::class.': '.$e->getMessage());
        }

        return true;
    }

    /** The grid a run would produce: kept lessons + generated rows, audited and scored. */
    private function outcome(TimetableBoard $board, SolverProblem $problem, SolverResult $result): array
    {
        $rows = PlacementRows::from($problem, $result);
        $fixed = array_flip(array_map(static fn (array $f): int => (int) substr($f['key'], 1), $problem->fixed));
        $lessonPeriods = array_flip($board->lessonPeriodIds);
        $replaced = [];
        $kept = [];
        foreach ($board->schedules as $s) {
            $compiled = isset($lessonPeriods[$s['period_id']]) && in_array($s['day_of_week'], $board->settings->days, true);
            if ($compiled && ! isset($fixed[$s['id']])) {
                $replaced[] = $s['id'];
            } else {
                $kept[] = $s;
            }
        }
        $proposed = self::asSchedules($rows);
        $verified = $board->withSchedules([...$kept, ...$proposed]);
        $issues = $this->auditor->audit($verified);
        $quality = $this->quality->score($verified, $issues);
        $quality['issues'] = array_count_values(array_column($issues, 'severity'));

        return [
            'result' => [
                'rows' => $rows,
                'replaced_ids' => $replaced,
                'grid_fingerprint' => self::gridFingerprint($board->schedules),
                'unplaced' => self::describeUnplaced($problem, $result),
                'violations' => $result->violations,
                'compile_notes' => $problem->compileNotes,
                'issues' => array_slice($issues, 0, 200),
                'stats' => ['iterations' => $result->iterations, 'elapsed_ms' => $result->elapsedMs, 'stopped' => $result->stopped,
                    'cards' => count($problem->cards), 'fixed' => count($problem->fixed), 'rows' => count($rows), 'replaced' => count($replaced)],
            ],
            'quality' => $quality,
            'hard_violations' => $result->hardViolations,
            'soft_penalty' => $result->softPenalty,
            'activities_total' => count($problem->cards),
            'placed' => count($result->placements),
            'unplaced' => count($result->unplaced),
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function describeUnplaced(SolverProblem $problem, SolverResult $result): array
    {
        $out = [];
        foreach ($result->unplaced as $cardId => $diagnosis) {
            $card = $problem->cards[$cardId];
            $out[] = [
                'card_id' => $cardId,
                'activity_id' => $card->activityId,
                'subject_id' => $card->subjectId,
                'teacher_id' => $card->leadTeacherId,
                'section_ids' => $card->sectionIds(),
                'length' => $card->length,
                'reasons' => $diagnosis['reasons'],
                'suggestions' => $diagnosis['suggestions'],
            ];
        }

        return $out;
    }

    /** Hash of the active grid (ids · slots · locks): applying a run fails when the grid changed since. */
    public static function gridFingerprint(array $schedules): string
    {
        $parts = array_map(static fn (array $s): string => $s['id'].':'.$s['day_of_week'].':'.$s['period_id'].':'.(int) ($s['locked'] ?? false), $schedules);
        sort($parts);

        return hash('sha256', implode('|', $parts));
    }

    /** @return list<array<string, mixed>> */
    private static function asSchedules(array $rows): array
    {
        $out = [];
        foreach ($rows as $n => $row) {
            $out[] = ['id' => -1 - $n, 'joined_to' => $row['is_lead'] ? null : -1] + $row;
        }

        return $out;
    }

    /** The input as it was (spec §66): enough to understand and reproduce the run. */
    private static function snapshot(TimetableBoard $board, array $run): array
    {
        return [
            'settings' => $board->settings->toArray(),
            'periods' => array_map(static fn ($p): array => ['id' => $p->id, 'number' => $p->periodNumber, 'start' => $p->startTime, 'end' => $p->endTime, 'type' => $p->periodType], $board->periods),
            'requirements' => $board->requirements,
            'activities' => $board->activities,
            'groups' => array_values($board->groups),
            'availability' => $board->availability,
            'rules' => $board->rules,
            'schedules' => $board->schedules,
            'mode' => $run['mode'],
            'scope' => $run['scope'],
            'options' => $run['options'],
        ];
    }
}
