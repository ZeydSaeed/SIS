<?php

namespace App\Domain\Timetable\Solver;

use App\Domain\Timetable\ValueObjects\GenerationMode;

/**
 * Everything a solver needs, compiled from the SIS projection ({@see ConstraintCompiler}). Plain data —
 * any solver behind {@see TimetableSolverInterface} can read it.
 *
 * Time: `days` × `periodIds` (lesson periods in day order, addressed by index) × `weeks` of the cycle.
 * `joins[i]` = lesson i and i+1 may form one block (no long break between them).
 *
 * Occupancy that never moves: `fixed` (locked lessons, lessons outside the scope, lessons kept by optimize).
 * Resource keys: 'T{teacherId}', 'S{sectionId}', 'R{roomId}', 'W{workshopId}'.
 *
 * Rules (each compiled rule: id|null, type, kind, hard, weight, params, scope, source):
 *   teacherRules[teacherId]          teacher_day / teacher_week rules resolved for that teacher
 *   sectionRules[sectionId]          section_day rules resolved for that section (not subject-bound)
 *   subjectRules[sectionId][subject] subject_max_per_day / subject_not_consecutive_days for that pair
 *   placementRules[cardId]           forbidden / preferred slot rules that match the card
 *   pairRules                        activity pairs (before / same day / not same day / not same time)
 *   teacherPairRules[teacherId]      teachers_not_simultaneous owned by that teacher
 * Availability: unavailable (hard) / avoid / preferred maps of resource key → "week:day:index" → true.
 */
final readonly class SolverProblem
{
    /**
     * @param  list<int>  $days
     * @param  list<int>  $periodIds
     * @param  array<int, bool>  $joins
     * @param  array<string, SolverCard>  $cards
     * @param  list<array{key: string, teachers: list<int>, targets: list<array{0: int, 1: int|null}>, facility: string|null, day: int, index: int, weeks: list<int>, subject_id: int, activity_key: string|null}>  $fixed
     * @param  array<int, int>  $groupDivision  group id → division id
     * @param  array<string, array<string, true>>  $unavailable
     * @param  array<string, array<string, true>>  $avoid
     * @param  array<string, array<string, true>>  $preferred
     * @param  array<int, list<array<string, mixed>>>  $teacherRules
     * @param  array<int, list<array<string, mixed>>>  $sectionRules
     * @param  array<int, array<int, list<array<string, mixed>>>>  $subjectRules
     * @param  array<string, list<array<string, mixed>>>  $placementRules
     * @param  list<array<string, mixed>>  $pairRules
     * @param  array<int, list<array<string, mixed>>>  $teacherPairRules
     * @param  array<string, int>  $availabilityWeights  'avoid' / 'preferred' → weight
     * @param  list<array{card_id: string, reason: string}>  $compileNotes
     */
    public function __construct(
        public GenerationMode $mode,
        public array $days,
        public int $weeks,
        public array $periodIds,
        public array $joins,
        public array $cards,
        public array $fixed,
        public array $groupDivision,
        public array $unavailable,
        public array $avoid,
        public array $preferred,
        public array $teacherRules,
        public array $sectionRules,
        public array $subjectRules,
        public array $placementRules,
        public array $pairRules,
        public array $teacherPairRules,
        public array $availabilityWeights,
        public array $compileNotes = [],
    ) {}

    public function periodCount(): int
    {
        return count($this->periodIds);
    }

    /** True when `length` lessons starting at `index` form one unbroken block. */
    public function blockFits(int $index, int $length): bool
    {
        if ($index < 0 || $index + $length > count($this->periodIds)) {
            return false;
        }
        for ($i = $index; $i < $index + $length - 1; $i++) {
            if (! ($this->joins[$i] ?? false)) {
                return false;
            }
        }

        return true;
    }
}
