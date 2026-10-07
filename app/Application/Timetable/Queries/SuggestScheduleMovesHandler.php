<?php

namespace App\Application\Timetable\Queries;

use App\Application\Timetable\DTOs\TimetableInsightDTO;
use App\Application\Timetable\Support\MoveSuggestionMapper;
use App\Application\Timetable\Support\TimetableBoardLoader;
use App\Domain\Timetable\Solver\ConstraintCompiler;
use App\Domain\Timetable\Solver\HeuristicTimetableSolver;
use App\Domain\Timetable\ValueObjects\GenerationMode;

/**
 * «اقتراح أماكن» (intelligent swap, spec §34): for one single lesson, the best free slots and same-length swaps
 * in its section, each with its effect (Δ penalty) — computed by the solver's own cost model. A lesson of a
 * longer block (double / triple) moves as a block through generation, not here.
 */
final class SuggestScheduleMovesHandler
{
    public function __construct(
        private readonly TimetableBoardLoader $boards,
        private readonly ConstraintCompiler $compiler,
        private readonly HeuristicTimetableSolver $solver,
        private readonly MoveSuggestionMapper $mapper,
    ) {}

    public function handle(SuggestScheduleMovesQuery $query): ?TimetableInsightDTO
    {
        $board = $this->boards->load($query->schoolId, $query->academicYearId);
        $lesson = $this->mapper->lesson($board, $query->scheduleId);
        if ($lesson === null) {
            return null;
        }
        $refusal = $this->mapper->refusal($lesson);
        if ($refusal !== null) {
            return new TimetableInsightDTO(['options' => [], 'reason' => $refusal]);
        }
        $problem = $this->compiler->compile($board, GenerationMode::Optimize, ['section_ids' => [$lesson['section_id']]]);
        $card = $this->mapper->cardOf($problem, $lesson);
        if ($card === null || $card->length > 1) {
            return new TimetableInsightDTO(['options' => [], 'reason' => $card === null ? 'not_found' : 'block']);
        }

        return new TimetableInsightDTO([
            'options' => $this->mapper->toGrid($board, $problem, $this->solver->suggestMoves($problem, $card->id, 8)),
            'reason' => null,
        ]);
    }
}
