<?php

namespace App\Domain\Timetable\Solver;

use App\Domain\Timetable\ValueObjects\GenerationMode;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * The PHP heuristic behind {@see TimetableSolverInterface} (decision D5).
 *
 * 1. Fixed occupancy first (locked lessons, lessons outside the scope / kept by optimize).
 * 2. Optimize / repair: blocks start where they sit on the grid; repair lifts the ones in conflict.
 * 3. Construction: the most constrained block first (fewest free positions, longest, most targets), at the
 *    position with the lowest penalty among positions that break no hard rule. Stuck → ejection chain: lift
 *    up to two blocks in the way, place, re-place the lifted ones elsewhere, or undo.
 * 4. Improvement: seeded simulated annealing over moves and swaps; a move never breaks a hard rule and never
 *    unplaces a block. Unplaced blocks are retried along the way. The best state found is returned.
 * 5. Diagnosis: for every block left unplaced, why each position was rejected and which single blocker
 *    alone stands in the way of some position (the minimal relaxation hint).
 *
 * Pure PHP, deterministic for a given seed and iteration cap.
 */
final class HeuristicTimetableSolver implements TimetableSolverInterface
{
    private SolverProblem $p;

    private Randomizer $random;

    /** @var array<int, array<int, array<int, array<int, string>>>> teacher → week → day → index → occupant */
    private array $tSlot = [];

    /** @var array<string, array<int, array<int, array<int, string>>>> facility → week → day → index → occupant */
    private array $fSlot = [];

    /** @var array<int, array<int, array<int, array<int, array<string, mixed>>>>> section → week → day → index → lane */
    private array $sSlot = [];

    /** @var array<int, array<int, array<int, array<int, array<int, int>>>>> section → week → day → subject → index → refs */
    private array $subj = [];

    /** @var array<string, array{0: int, 1: int, 2: string|null}> */
    private array $pos = [];

    /** @var array<string, array<string, array{0: int, 1: int, 2: int}>> activity → occupant → [day, index, length] */
    private array $actPos = [];

    /** @var array<int, int> */
    private array $dayOrder = [];

    /** @var array<string, list<int>> activity key → pair rule indexes */
    private array $pairsByActivity = [];

    /** @var array<int, list<int>> teacher → owners of teachers_not_simultaneous rules naming them */
    private array $pairOwners = [];

    /** @var array<string, int> activity key → blocks */
    private array $activityCards = [];

    /** @var array<string, array{0: array<string, mixed>, 1: int}>|null rule key → [rule, violations] while collecting */
    private ?array $collector = null;

    /** @var list<array{0: string, 1: string, 2?: array{0: int, 1: int, 2: string|null}}> undo log of occupy ('o') / vacate ('v') */
    private array $journal = [];

    private bool $journaling = false;

    private int $deadline = PHP_INT_MAX;

    public function name(): string
    {
        return 'heuristic-v1';
    }

    public function solve(SolverProblem $problem, SolverOptions $options, ?SolverProgress $progress = null): SolverResult
    {
        $started = hrtime(true);
        $deadline = $started + (int) ($options->timeBudgetSeconds * 1e9);
        $this->deadline = $deadline;
        $this->p = $problem;
        $this->random = new Randomizer(new Mt19937($options->seed));
        $this->prepare();
        $this->reset();

        $cards = $problem->cards;
        $total = count($cards);
        $unplaced = [];
        foreach ($cards as $id => $card) {
            if ($card->blockedReason !== null) {
                continue;
            }
            if ($card->initial !== null && $this->canStand($card, ...$card->initial)) {
                $this->occupyCard($card, ...$card->initial);
            } else {
                $unplaced[$id] = true;
            }
        }
        if ($problem->mode === GenerationMode::Repair) { // lift what breaks a hard rule, place it again
            foreach ($this->hardBreakers() as $id) {
                $this->vacateCard($cards[$id]);
                $unplaced[$id] = true;
            }
        }

        $stopped = false;
        $order = $this->difficultyOrder(array_keys($unplaced));
        $done = 0;
        while ($order !== []) {
            $id = array_shift($order);
            $card = $cards[$id];
            if ($this->placeBest($card) || $this->eject($card, $options->ejectionAttempts)) {
                unset($unplaced[$id]);
            }
            if (++$done % 25 === 0) {
                $progress?->report($total - count($unplaced) - $this->blockedCount(), $total, 0, 0);
                if ($progress?->shouldStop() || hrtime(true) > $deadline) {
                    $stopped = $progress?->shouldStop() ?? false;
                    break;
                }
                // Re-rank the rest from time to time (more often near the end, where it matters most).
                if (count($order) > 1 && $done % max(25, intdiv(count($order), 8)) < 25) {
                    $order = $this->difficultyOrder($order);
                }
            }
        }
        foreach ($order as $id) {
            $unplaced[$id] = true;
        }

        $iterations = 0;
        $best = $this->snapshot($unplaced);
        $movable = array_values(array_filter(array_keys($cards), fn (string $id): bool => isset($this->pos[$id])));
        $temperature = 2000.0;
        while (! $stopped && $iterations < $options->maxIterations && $movable !== []) {
            $iterations++;
            if ($iterations % 50 === 0) {
                if (hrtime(true) > $deadline) {
                    break;
                }
                if ($progress?->shouldStop()) {
                    $stopped = true;
                    break;
                }
            }
            if ($iterations % 200 === 0 && $unplaced !== []) {
                foreach (array_keys($unplaced) as $id) {
                    if ($this->placeBest($cards[$id]) || $this->eject($cards[$id], 12)) {
                        unset($unplaced[$id]);
                        $movable[] = $id;
                    }
                }
            }
            $temperature = max(1.0, $temperature * 0.9995);
            $id = $movable[$this->random->getInt(0, count($movable) - 1)];
            if ($this->random->getInt(0, 9) < 3) {
                $this->trySwap($cards[$id], $temperature);
            } else {
                $this->tryMove($cards[$id], $temperature);
            }
            if ($iterations % 100 === 0) {
                $current = $this->snapshot($unplaced);
                if ($this->better($current, $best)) {
                    $best = $current;
                }
                if ($iterations % 500 === 0) {
                    $progress?->report($total - count($unplaced) - $this->blockedCount(), $total, $best['hard'], $best['soft']);
                }
            }
        }
        $final = $this->snapshot($unplaced);
        if ($this->better($best, $final)) {
            $this->restore($best);
            $unplaced = $best['unplaced'];
        }
        // Last push for what is still unplaced: deeper chains, more attempts.
        foreach (array_keys($unplaced) as $id) {
            if (! $stopped && ($this->placeBest($cards[$id]) || $this->eject($cards[$id], $options->ejectionAttempts * 2, 3))) {
                unset($unplaced[$id]);
            }
        }

        $diagnosis = [];
        foreach ($cards as $id => $card) {
            if ($card->blockedReason !== null) {
                $diagnosis[$id] = ['reasons' => [$card->blockedReason => 1], 'suggestions' => [['reason' => $card->blockedReason]]];
            } elseif (isset($unplaced[$id])) {
                $diagnosis[$id] = $this->diagnose($card);
            }
        }

        [$hard, $soft, $violations] = $this->totals(true);
        $progress?->report(count($this->pos), $total, $hard, $soft);

        return new SolverResult(
            placements: $this->pos,
            unplaced: $diagnosis,
            violations: $violations,
            hardViolations: $hard,
            softPenalty: $soft,
            iterations: $iterations,
            elapsedMs: (int) ((hrtime(true) - $started) / 1e6),
            stopped: $stopped,
        );
    }

    /**
     * «اقتراح أماكن» (intelligent swap): for one placed block of an optimize-mode problem, every position it
     * could go — a free move, or a swap with the one same-length block of its section in the way — with the
     * change in hard violations and soft penalty. Best first; a hard increase is never proposed.
     *
     * @return list<array{kind: string, day: int, index: int, facility: string|null, with: string|null, hard: int, soft: int}>
     */
    public function suggestMoves(SolverProblem $problem, string $cardId, int $limit = 8): array
    {
        $this->p = $problem;
        $this->random = new Randomizer(new Mt19937(1));
        $this->prepare();
        $this->reset();
        foreach ($problem->cards as $card) {
            if ($card->initial !== null && $card->blockedReason === null && $this->blockers($card, ...$card->initial) === []) {
                $this->occupyCard($card, ...$card->initial);
            }
        }
        $c = $problem->cards[$cardId] ?? null;
        if ($c === null || ! isset($this->pos[$cardId])) {
            return [];
        }
        [$d0, $i0] = $this->pos[$cardId];
        $options = [];
        foreach ($problem->days as $d) {
            for ($i = 0; $i <= $problem->periodCount() - $c->length; $i++) {
                if ($d === $d0 && $i === $i0) {
                    continue;
                }
                foreach ($this->facilityOptions($c) as $f) {
                    $occupants = [];
                    $reasons = $this->blockers($c, $d, $i, $f, $occupants);
                    if ($reasons === []) {
                        [$hard, $soft] = $this->moveDelta($c, $d, $i, $f);
                        if ($hard <= 0) {
                            $options[] = ['kind' => 'move', 'day' => $d, 'index' => $i, 'facility' => $f, 'with' => null, 'hard' => $hard, 'soft' => $soft];
                        }
                        break;
                    }
                    $other = count($occupants) === 1 ? array_key_first($occupants) : null;
                    if ($other !== null && isset($this->pos[$other]) && $problem->cards[$other]->length === $c->length
                        && $problem->cards[$other]->targets[0][0] === $c->targets[0][0]) {
                        $delta = $this->swapDelta($c, $problem->cards[$other]);
                        if ($delta !== null && $delta[0] <= 0) {
                            $options[] = ['kind' => 'swap', 'day' => $d, 'index' => $i, 'facility' => $this->pos[$other][2], 'with' => $other, 'hard' => $delta[0], 'soft' => $delta[1]];
                        }
                        break;
                    }
                }
            }
        }
        usort($options, static fn (array $a, array $b): int => [$a['hard'], $a['soft'], $a['kind'], $a['day'], $a['index']] <=> [$b['hard'], $b['soft'], $b['kind'], $b['day'], $b['index']]);

        return array_slice($options, 0, $limit);
    }

    /** [hard, soft] change of trading the places of two blocks, or null when either cannot stand there. */
    private function swapDelta(SolverCard $c, SolverCard $o): ?array
    {
        [$cd, $ci, $cf] = $this->pos[$c->id];
        [$od, $oi, $of] = $this->pos[$o->id];
        $keys = array_unique([...$this->profileKeys($c, [$cd, $od]), ...$this->profileKeys($o, [$cd, $od])]);
        [$h0, $s0] = $this->sumKeys($keys);
        $p0 = $this->placementCost($c, $cd, $ci, $cf);
        $q0 = $this->placementCost($o, $od, $oi, $of);
        $this->vacateCard($c);
        $this->vacateCard($o);
        $result = null;
        if ($this->blockers($c, $od, $oi, $of) === []) {
            $this->occupyCard($c, $od, $oi, $of);
            if ($this->blockers($o, $cd, $ci, $cf) === []) {
                $this->occupyCard($o, $cd, $ci, $cf);
                [$h1, $s1] = $this->sumKeys($keys);
                $p1 = $this->placementCost($c, $od, $oi, $of);
                $q1 = $this->placementCost($o, $cd, $ci, $cf);
                $result = [$h1 + $p1[0] + $q1[0] - $h0 - $p0[0] - $q0[0], $s1 + $p1[1] + $q1[1] - $s0 - $p0[1] - $q0[1]];
                $this->vacateCard($o);
            }
            $this->vacateCard($c);
        }
        $this->occupyCard($c, $cd, $ci, $cf);
        $this->occupyCard($o, $od, $oi, $of);

        return $result;
    }

    // ── Setup ────────────────────────────────────────────────────────────────

    private function prepare(): void
    {
        $this->dayOrder = array_flip($this->p->days);
        $this->pairsByActivity = [];
        foreach ($this->p->pairRules as $index => $rule) {
            $this->pairsByActivity[$rule['a']][] = $index;
            $this->pairsByActivity[$rule['b']][] = $index;
        }
        $this->pairOwners = [];
        foreach ($this->p->teacherPairRules as $owner => $rules) {
            foreach ($rules as $rule) {
                $this->pairOwners[(int) $rule['params']['other_teacher_id']][] = $owner;
            }
        }
        $this->activityCards = [];
        foreach ($this->p->cards as $card) {
            $this->activityCards[$card->activityKey] = ($this->activityCards[$card->activityKey] ?? 0) + 1;
        }
    }

    private function reset(): void
    {
        $this->tSlot = $this->fSlot = $this->sSlot = $this->subj = $this->pos = $this->actPos = [];
        foreach ($this->p->fixed as $f) {
            foreach ($f['weeks'] as $w) {
                foreach ($f['teachers'] as $t) {
                    $this->tSlot[$t][$w][$f['day']][$f['index']] = $f['key'];
                }
                if ($f['facility'] !== null) {
                    $this->fSlot[$f['facility']][$w][$f['day']][$f['index']] = $f['key'];
                }
                foreach ($f['targets'] as [$s, $g]) {
                    $this->setLane($s, $g, $w, $f['day'], $f['index'], $f['key']);
                    $this->subj[$s][$w][$f['day']][$f['subject_id']][$f['index']] = ($this->subj[$s][$w][$f['day']][$f['subject_id']][$f['index']] ?? 0) + 1;
                }
            }
            if ($f['activity_key'] !== null) {
                $this->actPos[$f['activity_key']][$f['key']] = [$f['day'], $f['index'], 1];
            }
        }
    }

    private function blockedCount(): int
    {
        return count(array_filter($this->p->cards, static fn (SolverCard $c): bool => $c->blockedReason !== null));
    }

    // ── Occupancy ────────────────────────────────────────────────────────────

    private function laneFree(int $s, ?int $g, int $w, int $d, int $k): bool
    {
        $lane = $this->sSlot[$s][$w][$d][$k] ?? null;
        if ($lane === null) {
            return true;
        }
        if ($g === null || isset($lane['full'])) {
            return false;
        }
        $division = $this->p->groupDivision[$g] ?? null;

        return $division !== null && $lane['div'] === $division && ! isset($lane['g'][$g]);
    }

    /** @return list<string> occupants of a section slot that block group `g` */
    private function laneOccupants(int $s, ?int $g, int $w, int $d, int $k): array
    {
        $lane = $this->sSlot[$s][$w][$d][$k] ?? null;
        if ($lane === null) {
            return [];
        }
        if (isset($lane['full'])) {
            return [$lane['full']];
        }
        $division = $g === null ? null : ($this->p->groupDivision[$g] ?? null);
        if ($division !== null && $lane['div'] === $division) {
            return isset($lane['g'][$g]) ? [$lane['g'][$g]] : [];
        }

        return array_values($lane['g']);
    }

    private function setLane(int $s, ?int $g, int $w, int $d, int $k, string $key): void
    {
        $division = $g === null ? null : ($this->p->groupDivision[$g] ?? null);
        if ($division === null) {
            $this->sSlot[$s][$w][$d][$k] = ['full' => $key];

            return;
        }
        $this->sSlot[$s][$w][$d][$k] ??= ['div' => $division, 'g' => []];
        $this->sSlot[$s][$w][$d][$k]['g'][$g] = $key;
    }

    private function clearLane(int $s, ?int $g, int $w, int $d, int $k): void
    {
        $lane = $this->sSlot[$s][$w][$d][$k] ?? null;
        if ($lane === null) {
            return;
        }
        if (isset($lane['full']) || $g === null) {
            unset($this->sSlot[$s][$w][$d][$k]);

            return;
        }
        unset($this->sSlot[$s][$w][$d][$k]['g'][$g]);
        if ($this->sSlot[$s][$w][$d][$k]['g'] === []) {
            unset($this->sSlot[$s][$w][$d][$k]);
        }
    }

    private function occupyCard(SolverCard $c, int $d, int $i, ?string $f): void
    {
        foreach ($c->weeks as $w) {
            for ($k = $i; $k < $i + $c->length; $k++) {
                foreach ($c->teachers as $t) {
                    $this->tSlot[$t][$w][$d][$k] = $c->id;
                }
                if ($f !== null) {
                    $this->fSlot[$f][$w][$d][$k] = $c->id;
                }
                foreach ($c->targets as [$s, $g]) {
                    $this->setLane($s, $g, $w, $d, $k, $c->id);
                    $this->subj[$s][$w][$d][$c->subjectId][$k] = ($this->subj[$s][$w][$d][$c->subjectId][$k] ?? 0) + 1;
                }
            }
        }
        $this->pos[$c->id] = [$d, $i, $f];
        $this->actPos[$c->activityKey][$c->id] = [$d, $i, $c->length];
        if ($this->journaling) {
            $this->journal[] = ['o', $c->id];
        }
    }

    /** Undoes every occupy / vacate logged since `mark` (count of the journal when the attempt began). */
    private function rollback(int $mark): void
    {
        $this->journaling = false;
        while (count($this->journal) > $mark) {
            $entry = array_pop($this->journal);
            $card = $this->p->cards[$entry[1]];
            if ($entry[0] === 'o') {
                $this->vacateCard($card);
            } else {
                $this->occupyCard($card, ...$entry[2]);
            }
        }
        $this->journaling = true;
    }

    private function vacateCard(SolverCard $c): void
    {
        if (! isset($this->pos[$c->id])) {
            return;
        }
        [$d, $i, $f] = $this->pos[$c->id];
        if ($this->journaling) {
            $this->journal[] = ['v', $c->id, [$d, $i, $f]];
        }
        foreach ($c->weeks as $w) {
            for ($k = $i; $k < $i + $c->length; $k++) {
                foreach ($c->teachers as $t) {
                    unset($this->tSlot[$t][$w][$d][$k]);
                }
                if ($f !== null) {
                    unset($this->fSlot[$f][$w][$d][$k]);
                }
                foreach ($c->targets as [$s, $g]) {
                    $this->clearLane($s, $g, $w, $d, $k);
                    $left = ($this->subj[$s][$w][$d][$c->subjectId][$k] ?? 1) - 1;
                    if ($left <= 0) {
                        unset($this->subj[$s][$w][$d][$c->subjectId][$k]);
                        if (($this->subj[$s][$w][$d][$c->subjectId] ?? []) === []) {
                            unset($this->subj[$s][$w][$d][$c->subjectId]);
                        }
                    } else {
                        $this->subj[$s][$w][$d][$c->subjectId][$k] = $left;
                    }
                }
            }
        }
        unset($this->pos[$c->id], $this->actPos[$c->activityKey][$c->id]);
    }

    /**
     * Why `c` cannot sit at (d, i, f): resource reasons ('teacher_busy:7', 'section_unavailable:3', …).
     *
     * @param  array<string, true>|null  $occupants  filled with the placed blocks standing in the way
     * @return list<string>
     */
    private function blockers(SolverCard $c, int $d, int $i, ?string $f, ?array &$occupants = null): array
    {
        if (! $this->p->blockFits($i, $c->length)) {
            return ['no_block'];
        }
        $reasons = [];
        foreach ($c->weeks as $w) {
            for ($k = $i; $k < $i + $c->length; $k++) {
                $slot = $w.':'.$d.':'.$k;
                foreach ($c->teachers as $t) {
                    if (isset($this->p->unavailable['T'.$t][$slot])) {
                        $reasons['teacher_unavailable:'.$t] = true;
                    }
                    $who = $this->tSlot[$t][$w][$d][$k] ?? null;
                    if ($who !== null && $who !== $c->id) {
                        $reasons['teacher_busy:'.$t] = true;
                        $occupants[$who] = true;
                    }
                }
                foreach ($c->targets as [$s, $g]) {
                    if (isset($this->p->unavailable['S'.$s][$slot])) {
                        $reasons['section_unavailable:'.$s] = true;
                    }
                    if (! $this->laneFree($s, $g, $w, $d, $k)) {
                        $others = array_filter($this->laneOccupants($s, $g, $w, $d, $k), static fn (string $o): bool => $o !== $c->id);
                        if ($others !== []) {
                            $reasons['section_busy:'.$s] = true;
                            foreach ($others as $o) {
                                $occupants[$o] = true;
                            }
                        }
                    }
                }
                if ($f !== null) {
                    if (isset($this->p->unavailable[$f][$slot])) {
                        $reasons['facility_unavailable:'.$f] = true;
                    }
                    $who = $this->fSlot[$f][$w][$d][$k] ?? null;
                    if ($who !== null && $who !== $c->id) {
                        $reasons['facility_busy:'.$f] = true;
                        $occupants[$who] = true;
                    }
                }
            }
        }

        return array_keys($reasons);
    }

    /** No resource clash and no hard rule broken by putting `c` there (c not placed). */
    private function canStand(SolverCard $c, int $d, int $i, ?string $f): bool
    {
        if (! in_array($d, $this->p->days, true) || ($c->facilities !== [] && ! in_array($f, $c->facilities, true)) || $this->blockers($c, $d, $i, $f) !== []) {
            return false;
        }
        [$hard] = $this->placeDelta($c, $d, $i, $f);

        return $hard <= 0;
    }

    /** @return list<string|null> */
    private function facilityOptions(SolverCard $c): array
    {
        return $c->facilities === [] ? [null] : $c->facilities;
    }

    // ── Construction ─────────────────────────────────────────────────────────

    /**
     * @param  list<string>  $ids
     * @return list<string>
     */
    private function difficultyOrder(array $ids): array
    {
        $score = [];
        foreach ($ids as $id) {
            $c = $this->p->cards[$id];
            $free = 0;
            foreach ($this->p->days as $d) {
                for ($i = 0; $i <= $this->p->periodCount() - $c->length; $i++) {
                    foreach ($this->facilityOptions($c) as $f) {
                        if ($this->blockers($c, $d, $i, $f) === []) {
                            $free++;
                            break;
                        }
                    }
                }
            }
            $score[$id] = [$free, -$c->length, -count($c->targets), -count($c->teachers), $id];
        }
        uasort($score, static fn (array $a, array $b): int => $a <=> $b);

        return array_keys($score);
    }

    /** Places `c` at its cheapest admissible position (no hard rule broken). */
    private function placeBest(SolverCard $c): bool
    {
        $best = null;
        foreach ($this->p->days as $d) {
            for ($i = 0; $i <= $this->p->periodCount() - $c->length; $i++) {
                foreach ($this->facilityOptions($c) as $f) {
                    if ($this->blockers($c, $d, $i, $f) !== []) {
                        continue;
                    }
                    [$hard, $soft] = $this->placeDelta($c, $d, $i, $f);
                    if ($hard > 0) {
                        continue;
                    }
                    $rank = $soft + $this->random->getInt(0, 3);
                    if ($best === null || $rank < $best[0]) {
                        $best = [$rank, $d, $i, $f];
                    }
                }
            }
        }
        if ($best === null) {
            return false;
        }
        $this->occupyCard($c, $best[1], $best[2], $best[3]);

        return true;
    }

    /**
     * Ejection chain: lift up to three placed blocks in the way, place `c`, re-place the lifted blocks
     * (themselves by ejection while `depth` allows) — or undo exactly what the attempt changed (journal).
     */
    private function eject(SolverCard $c, int $attempts, int $depth = 2): bool
    {
        $this->journaling = true;
        $this->journal = [];
        try {
            return $this->ejectInner($c, $attempts, $depth);
        } finally {
            $this->journaling = false;
            $this->journal = [];
        }
    }

    private function ejectInner(SolverCard $c, int $attempts, int $depth): bool
    {
        $candidates = [];
        foreach ($this->p->days as $d) {
            for ($i = 0; $i <= $this->p->periodCount() - $c->length; $i++) {
                foreach ($this->facilityOptions($c) as $f) {
                    $occupants = [];
                    $reasons = $this->blockers($c, $d, $i, $f, $occupants);
                    if ($reasons === [] || count($occupants) > 3) {
                        continue;
                    }
                    $movable = true;
                    foreach ($reasons as $reason) {
                        if (! str_contains($reason, '_busy:')) {
                            $movable = false;
                            break;
                        }
                    }
                    foreach (array_keys($occupants) as $o) {
                        if (! isset($this->pos[$o])) {
                            $movable = false; // fixed lesson
                        }
                    }
                    if ($movable) {
                        $candidates[] = [count($occupants), $this->random->getInt(0, 1000), $d, $i, $f, array_keys($occupants)];
                    }
                }
            }
        }
        usort($candidates, static fn (array $a, array $b): int => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        foreach (array_slice($candidates, 0, $attempts) as [, , $d, $i, $f, $lifted]) {
            if (hrtime(true) > $this->deadline) {
                return false;
            }
            $mark = count($this->journal);
            foreach ($lifted as $o) {
                $this->vacateCard($this->p->cards[$o]);
            }
            if ($this->canStand($c, $d, $i, $f)) {
                $this->occupyCard($c, $d, $i, $f);
                $ok = true;
                foreach ($lifted as $o) {
                    $card = $this->p->cards[$o];
                    if (! $this->placeBest($card) && ! ($depth > 1 && $this->ejectInner($card, max(4, intdiv($attempts, 3)), $depth - 1))) {
                        $ok = false;
                        break;
                    }
                }
                if ($ok) {
                    return true;
                }
            }
            $this->rollback($mark);
        }

        return false;
    }

    // ── Improvement ──────────────────────────────────────────────────────────

    private function tryMove(SolverCard $c, float $temperature): void
    {
        [$d0, $i0, $f0] = $this->pos[$c->id];
        $best = null;
        for ($n = 0; $n < 12; $n++) {
            $d = $this->p->days[$this->random->getInt(0, count($this->p->days) - 1)];
            $i = $this->random->getInt(0, max(0, $this->p->periodCount() - $c->length));
            $options = $this->facilityOptions($c);
            $f = $options[$this->random->getInt(0, count($options) - 1)];
            if (($d === $d0 && $i === $i0 && $f === $f0) || $this->blockers($c, $d, $i, $f) !== []) {
                continue;
            }
            [$hard, $soft] = $this->moveDelta($c, $d, $i, $f);
            if ($hard <= 0 && ($best === null || $soft < $best[0])) {
                $best = [$soft, $d, $i, $f, $hard];
            }
        }
        if ($best === null) {
            return;
        }
        if ($best[4] < 0 || $best[0] <= 0 || $this->random->nextFloat() < exp(-$best[0] / $temperature)) {
            $this->vacateCard($c);
            $this->occupyCard($c, $best[1], $best[2], $best[3]);
        }
    }

    /** Two placed blocks of one section with the same length trade places. */
    private function trySwap(SolverCard $c, float $temperature): void
    {
        $section = $c->targets[0][0];
        $peers = [];
        foreach ($this->pos as $id => $where) {
            $o = $this->p->cards[$id];
            if ($id !== $c->id && $o->length === $c->length && $o->targets[0][0] === $section) {
                $peers[] = $id;
            }
        }
        if ($peers === []) {
            return;
        }
        $o = $this->p->cards[$peers[$this->random->getInt(0, count($peers) - 1)]];
        [$cd, $ci, $cf] = $this->pos[$c->id];
        [$od, $oi, $of] = $this->pos[$o->id];
        if ($cd === $od && $ci === $oi) {
            return;
        }
        $keys = array_unique([...$this->profileKeys($c, [$cd, $od]), ...$this->profileKeys($o, [$cd, $od])]);
        [$h0, $s0] = $this->sumKeys($keys);
        [$ph0, $ps0] = $this->placementCost($c, $cd, $ci, $cf);
        [$qh0, $qs0] = $this->placementCost($o, $od, $oi, $of);
        $this->vacateCard($c);
        $this->vacateCard($o);
        $cfNew = $c->facilities === [] ? null : (in_array($of, $c->facilities, true) ? $of : $cf);
        $ofNew = $o->facilities === [] ? null : (in_array($cf, $o->facilities, true) ? $cf : $of);
        $fits = $this->blockers($c, $od, $oi, $cfNew) === [];
        if ($fits) {
            $this->occupyCard($c, $od, $oi, $cfNew);
            $fits = $this->blockers($o, $cd, $ci, $ofNew) === [];
            if ($fits) {
                $this->occupyCard($o, $cd, $ci, $ofNew);
                [$h1, $s1] = $this->sumKeys($keys);
                [$ph1, $ps1] = $this->placementCost($c, $od, $oi, $cfNew);
                [$qh1, $qs1] = $this->placementCost($o, $cd, $ci, $ofNew);
                $dh = $h1 + $ph1 + $qh1 - $h0 - $ph0 - $qh0;
                $ds = $s1 + $ps1 + $qs1 - $s0 - $ps0 - $qs0;
                if ($dh < 0 || ($dh === 0 && ($ds <= 0 || $this->random->nextFloat() < exp(-$ds / $temperature)))) {
                    return;
                }
                $this->vacateCard($o);
            }
            $this->vacateCard($c);
        }
        $this->occupyCard($c, $cd, $ci, $cf);
        $this->occupyCard($o, $od, $oi, $of);
    }

    // ── Cost ─────────────────────────────────────────────────────────────────

    /** [hard, soft] change if `c` (not placed) is put at (d, i, f). */
    private function placeDelta(SolverCard $c, int $d, int $i, ?string $f): array
    {
        $keys = $this->profileKeys($c, [$d]);
        [$h0, $s0] = $this->sumKeys($keys);
        $this->occupyCard($c, $d, $i, $f);
        [$h1, $s1] = $this->sumKeys($keys);
        [$ph, $ps] = $this->placementCost($c, $d, $i, $f);
        $this->vacateCard($c);

        return [$h1 + $ph - $h0, $s1 + $ps - $s0];
    }

    /** [hard, soft] change if placed `c` moves to (d, i, f). */
    private function moveDelta(SolverCard $c, int $d, int $i, ?string $f): array
    {
        [$d0, $i0, $f0] = $this->pos[$c->id];
        $keys = $this->profileKeys($c, array_unique([$d0, $d]));
        [$h0, $s0] = $this->sumKeys($keys);
        [$ph0, $ps0] = $this->placementCost($c, $d0, $i0, $f0);
        $this->vacateCard($c);
        $this->occupyCard($c, $d, $i, $f);
        [$h1, $s1] = $this->sumKeys($keys);
        [$ph1, $ps1] = $this->placementCost($c, $d, $i, $f);
        $this->vacateCard($c);
        $this->occupyCard($c, $d0, $i0, $f0);

        return [$h1 + $ph1 - $h0 - $ph0, $s1 + $ps1 - $s0 - $ps0];
    }

    /**
     * Profiles a block touches on the given days.
     *
     * @param  list<int>  $days
     * @return list<string>
     */
    private function profileKeys(SolverCard $c, array $days): array
    {
        $keys = [];
        $teachers = $c->teachers;
        foreach ($c->teachers as $t) {
            foreach ($this->pairOwners[$t] ?? [] as $owner) {
                $teachers[] = $owner;
            }
        }
        foreach (array_unique($teachers) as $t) {
            foreach ($c->weeks as $w) {
                $keys[] = 'tw:'.$t.':'.$w;
                foreach ($days as $d) {
                    $keys[] = 'td:'.$t.':'.$w.':'.$d;
                }
            }
        }
        foreach ($c->sectionIds() as $s) {
            foreach ($c->weeks as $w) {
                $keys[] = 'sw:'.$s.':'.$w;
                foreach ($days as $d) {
                    $keys[] = 'sd:'.$s.':'.$w.':'.$d;
                }
            }
        }
        foreach ($this->pairsByActivity[$c->activityKey] ?? [] as $index) {
            $keys[] = 'pr:'.$index;
        }
        if (($this->activityCards[$c->activityKey] ?? 0) > 1) {
            $keys[] = 'sp:'.$c->activityKey;
        }

        return array_values(array_unique($keys));
    }

    /** @param  list<string>  $keys */
    private function sumKeys(array $keys): array
    {
        $hard = $soft = 0;
        foreach ($keys as $key) {
            [$h, $s] = $this->profileCost($key);
            $hard += $h;
            $soft += $s;
        }

        return [$hard, $soft];
    }

    private function profileCost(string $key): array
    {
        $parts = explode(':', $key, 2);

        return match ($parts[0]) {
            'td' => $this->teacherDayCost(...array_map('intval', explode(':', $parts[1]))),
            'tw' => $this->teacherWeekCost(...array_map('intval', explode(':', $parts[1]))),
            'sd' => $this->sectionDayCost(...array_map('intval', explode(':', $parts[1]))),
            'sw' => $this->sectionWeekCost(...array_map('intval', explode(':', $parts[1]))),
            'pr' => $this->pairCost((int) $parts[1]),
            'sp' => $this->spreadCost($parts[1]),
            default => [0, 0],
        };
    }

    /** Adds `v` violations of `rule` to [hard, soft] (and to the collector while collecting). */
    private function charge(array $rule, int $v, int &$hard, int &$soft): void
    {
        if ($v <= 0) {
            return;
        }
        if ($rule['hard']) {
            $hard += $v;
        } else {
            $soft += $v * $rule['weight'];
        }
        if ($this->collector !== null) {
            $key = $rule['type'].'#'.($rule['id'] ?? 's');
            $this->collector[$key] = [$rule, ($this->collector[$key][1] ?? 0) + $v];
        }
    }

    /** @param  array<int, string>  $slots  index → occupant */
    private static function runStats(array $slots): array
    {
        $positions = array_keys($slots);
        sort($positions);
        $n = count($positions);
        if ($n === 0) {
            return [0, 0, 0, []];
        }
        $gaps = $positions[$n - 1] - $positions[0] + 1 - $n;
        $longest = $run = 0;
        foreach ($positions as $j => $p) {
            $run = $j > 0 && $positions[$j - 1] === $p - 1 ? $run + 1 : 1;
            $longest = max($longest, $run);
        }

        return [$n, $gaps, $longest, $positions];
    }

    private function teacherDayCost(int $t, int $w, int $d): array
    {
        $hard = $soft = 0;
        $slots = $this->tSlot[$t][$w][$d] ?? [];
        [$n, $gaps, $longest] = self::runStats($slots);
        foreach ($this->p->teacherRules[$t] ?? [] as $rule) {
            $v = match ($rule['type']) {
                'teacher_max_per_day' => $n - (int) $rule['params']['max'],
                'teacher_min_per_day' => $n > 0 && $n < (int) $rule['params']['min'] ? (int) $rule['params']['min'] - $n : 0,
                'teacher_max_gaps_per_day' => $gaps - (int) $rule['params']['max'],
                'teacher_max_consecutive' => $longest - (int) $rule['params']['max'],
                default => 0,
            };
            $this->charge($rule, $v, $hard, $soft);
        }
        foreach ($this->p->teacherPairRules[$t] ?? [] as $rule) {
            $other = $this->tSlot[(int) $rule['params']['other_teacher_id']][$w][$d] ?? [];
            $this->charge($rule, count(array_intersect_key($slots, $other)), $hard, $soft);
        }

        return [$hard, $soft];
    }

    private function teacherWeekCost(int $t, int $w): array
    {
        $hard = $soft = 0;
        foreach ($this->p->teacherRules[$t] ?? [] as $rule) {
            if ($rule['type'] === 'teacher_max_days') {
                $days = count(array_filter($this->tSlot[$t][$w] ?? [], static fn (array $slots): bool => $slots !== []));
                $this->charge($rule, $days - (int) $rule['params']['max'], $hard, $soft);
            }
        }

        return [$hard, $soft];
    }

    private function sectionDayCost(int $s, int $w, int $d): array
    {
        $hard = $soft = 0;
        $slots = $this->sSlot[$s][$w][$d] ?? [];
        [$n, $gaps, , $positions] = self::runStats($slots);
        foreach ($this->p->sectionRules[$s] ?? [] as $rule) {
            $v = match ($rule['type']) {
                'section_max_per_day' => $n - (int) $rule['params']['max'],
                'section_min_per_day' => $n > 0 && $n < (int) $rule['params']['min'] ? (int) $rule['params']['min'] - $n : 0,
                'section_no_gaps' => $gaps,
                'section_end_by' => count(array_filter($positions, static fn (int $k): bool => $k + 1 > (int) $rule['params']['lesson'])),
                default => 0,
            };
            $this->charge($rule, $v, $hard, $soft);
        }
        foreach ($this->subj[$s][$w][$d] ?? [] as $subject => $periods) {
            foreach ($this->p->subjectRules[$s][$subject] ?? [] as $rule) {
                if ($rule['type'] === 'subject_max_per_day') {
                    $this->charge($rule, count($periods) - (int) $rule['params']['max'], $hard, $soft);
                }
            }
        }

        return [$hard, $soft];
    }

    private function sectionWeekCost(int $s, int $w): array
    {
        $hard = $soft = 0;
        foreach ($this->p->subjectRules[$s] ?? [] as $subject => $rules) {
            foreach ($rules as $rule) {
                if ($rule['type'] !== 'subject_not_consecutive_days') {
                    continue;
                }
                $v = 0;
                $previous = false;
                foreach ($this->p->days as $d) {
                    $has = isset($this->subj[$s][$w][$d][$subject]);
                    if ($has && $previous) {
                        $v++;
                    }
                    $previous = $has;
                }
                $this->charge($rule, $v, $hard, $soft);
            }
        }

        return [$hard, $soft];
    }

    private function pairCost(int $index): array
    {
        $rule = $this->p->pairRules[$index];
        $a = $this->actPos[$rule['a']] ?? [];
        $b = $this->actPos[$rule['b']] ?? [];
        $hard = $soft = 0;
        if ($a === [] || $b === []) {
            return [0, 0];
        }
        $order = fn (array $x): int => ($this->dayOrder[$x[0]] ?? 0) * 100 + $x[1];
        $v = 0;
        switch ($rule['type']) {
            case 'activity_before':
                foreach ($a as $x) {
                    foreach ($b as $y) {
                        if ($order($x) + $x[2] - 1 >= $order($y)) {
                            $v++;
                        }
                    }
                }
                break;
            case 'activity_same_day':
                $daysA = array_flip(array_column($a, 0));
                foreach ($b as $y) {
                    $v += isset($daysA[$y[0]]) ? 0 : 1;
                }
                break;
            case 'activity_not_same_day':
                $v = count(array_intersect_key(array_flip(array_column($a, 0)), array_flip(array_column($b, 0))));
                break;
            case 'activity_not_same_time':
                foreach ($a as $x) {
                    foreach ($b as $y) {
                        if ($x[0] === $y[0] && $x[1] < $y[1] + $y[2] && $y[1] < $x[1] + $x[2]) {
                            $v++;
                        }
                    }
                }
                break;
        }
        $this->charge($rule, $v, $hard, $soft);

        return [$hard, $soft];
    }

    /** Blocks of one activity on different days (built-in, MEDIUM). */
    private function spreadCost(string $activityKey): array
    {
        $perDay = [];
        foreach ($this->actPos[$activityKey] ?? [] as $x) {
            $perDay[$x[0]] = ($perDay[$x[0]] ?? 0) + 1;
        }
        $v = 0;
        foreach ($perDay as $count) {
            $v += max(0, $count - 1);
        }
        $hard = $soft = 0;
        $this->charge(['type' => 'activity_spread', 'id' => null, 'hard' => false, 'weight' => $this->p->availabilityWeights['avoid'], 'priority' => 4, 'source' => 'system'], $v, $hard, $soft);

        return [$hard, $soft];
    }

    /** Forbidden / preferred slot rules and avoid / preferred availability where the block sits. */
    private function placementCost(SolverCard $c, int $d, int $i, ?string $f): array
    {
        $hard = $soft = 0;
        foreach ($this->p->placementRules[$c->id] ?? [] as $rule) {
            $room = $rule['scope']['room_id'] ?? null;
            if ($room !== null && $f !== 'R'.$room) {
                continue;
            }
            $days = $rule['params']['days'] ?? [];
            $lessons = $rule['params']['lessons'] ?? [];
            $v = 0;
            for ($k = $i; $k < $i + $c->length; $k++) {
                $match = ($days === [] || in_array($d, $days, true)) && ($lessons === [] || in_array($k + 1, $lessons, true));
                $v += ($rule['type'] === 'forbidden_slots') === $match ? 1 : 0;
            }
            $this->charge($rule, $v, $hard, $soft);
        }
        $resources = array_map(static fn (int $t): string => 'T'.$t, $c->teachers);
        foreach ($c->sectionIds() as $s) {
            $resources[] = 'S'.$s;
        }
        if ($f !== null) {
            $resources[] = $f;
        }
        foreach ($resources as $resource) {
            $avoid = $this->p->avoid[$resource] ?? null;
            $preferred = $this->p->preferred[$resource] ?? null;
            if ($avoid === null && $preferred === null) {
                continue;
            }
            foreach ($c->weeks as $w) {
                for ($k = $i; $k < $i + $c->length; $k++) {
                    $slot = $w.':'.$d.':'.$k;
                    if ($avoid !== null && isset($avoid[$slot])) {
                        $soft += $this->p->availabilityWeights['avoid'];
                    }
                    if ($preferred !== null && ! isset($preferred[$slot])) {
                        $soft += $this->p->availabilityWeights['preferred'];
                    }
                }
            }
        }

        return [$hard, $soft];
    }

    /**
     * Whole-state cost; with `$collect`, also the violations per rule.
     *
     * @return array{0: int, 1: int, 2: list<array{rule_type: string, rule_id: int|null, source: string, hard: bool, count: int, priority: int}>}
     */
    private function totals(bool $collect = false): array
    {
        $this->collector = $collect ? [] : null;
        $hard = $soft = 0;
        $add = static function (array $cost) use (&$hard, &$soft): void {
            $hard += $cost[0];
            $soft += $cost[1];
        };
        $teachers = array_unique([...array_keys($this->p->teacherRules), ...array_keys($this->p->teacherPairRules)]);
        $sections = array_unique([...array_keys($this->p->sectionRules), ...array_keys($this->p->subjectRules)]);
        for ($w = 1; $w <= $this->p->weeks; $w++) {
            foreach ($teachers as $t) {
                $add($this->teacherWeekCost($t, $w));
                foreach ($this->p->days as $d) {
                    $add($this->teacherDayCost($t, $w, $d));
                }
            }
            foreach ($sections as $s) {
                $add($this->sectionWeekCost($s, $w));
                foreach ($this->p->days as $d) {
                    $add($this->sectionDayCost($s, $w, $d));
                }
            }
        }
        foreach (array_keys($this->p->pairRules) as $index) {
            $add($this->pairCost($index));
        }
        foreach ($this->activityCards as $key => $count) {
            if ($count > 1) {
                $add($this->spreadCost($key));
            }
        }
        foreach ($this->pos as $id => [$d, $i, $f]) {
            $add($this->placementCost($this->p->cards[$id], $d, $i, $f));
        }

        $violations = [];
        foreach ($this->collector ?? [] as [$rule, $count]) {
            $violations[] = ['rule_type' => $rule['type'], 'rule_id' => $rule['id'], 'source' => $rule['source'], 'hard' => $rule['hard'], 'count' => $count, 'priority' => $rule['priority']];
        }
        usort($violations, static fn (array $a, array $b): int => [$b['hard'], $a['priority'], -$a['count']] <=> [$a['hard'], $b['priority'], -$b['count']]);
        $this->collector = null;

        return [$hard, $soft, $violations];
    }

    /** Placed blocks that take part in a hard-rule violation (repair lifts them). */
    private function hardBreakers(): array
    {
        $ids = [];
        foreach ($this->pos as $id => [$d, $i, $f]) {
            $c = $this->p->cards[$id];
            $this->vacateCard($c);
            [$hard] = $this->placeDelta($c, $d, $i, $f);
            $this->occupyCard($c, $d, $i, $f);
            if ($hard > 0) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    // ── Best state ───────────────────────────────────────────────────────────

    /** @param  array<string, true>  $unplaced */
    private function snapshot(array $unplaced): array
    {
        [$hard, $soft] = $this->totals();

        return ['unplaced' => $unplaced, 'pos' => $this->pos, 'hard' => $hard, 'soft' => $soft];
    }

    private function better(array $a, array $b): bool
    {
        return [count($a['unplaced']), $a['hard'], $a['soft']] < [count($b['unplaced']), $b['hard'], $b['soft']];
    }

    private function restore(array $state): void
    {
        $this->reset();
        foreach ($state['pos'] as $id => [$d, $i, $f]) {
            $this->occupyCard($this->p->cards[$id], $d, $i, $f);
        }
    }

    // ── Diagnosis ────────────────────────────────────────────────────────────

    /** @return array{reasons: array<string, int>, suggestions: list<array<string, mixed>>} */
    private function diagnose(SolverCard $c): array
    {
        $reasons = [];
        $single = [];
        foreach ($this->p->days as $d) {
            for ($i = 0; $i < $this->p->periodCount(); $i++) {
                $found = [];
                if (! $this->p->blockFits($i, $c->length)) {
                    $found = ['no_block'];
                } else {
                    $facilityReasons = [];
                    $free = null;
                    $hasFree = false; // a block without a room is free with facility null
                    foreach ($this->facilityOptions($c) as $f) {
                        $why = $this->blockers($c, $d, $i, $f);
                        if ($why === []) {
                            $free = $f;
                            $hasFree = true;
                            break;
                        }
                        $facilityReasons[] = $why;
                    }
                    if (! $hasFree) {
                        $common = count($facilityReasons) === 1 ? $facilityReasons[0] : array_values(array_intersect(...$facilityReasons));
                        $found = $common !== [] ? $common : ['facility_busy'];
                        if (count($facilityReasons) > 1) {
                            $only = array_filter(array_merge(...$facilityReasons), static fn (string $r): bool => str_starts_with($r, 'facility_'));
                            if ($only !== [] && $common === []) {
                                $found = ['facility_busy'];
                            }
                        }
                    } else {
                        $this->collector = [];
                        $keys = $this->profileKeys($c, [$d]);
                        $this->sumKeys($keys);
                        $before = $this->collector;
                        $this->collector = [];
                        $this->occupyCard($c, $d, $i, $free);
                        $this->sumKeys($keys);
                        $this->placementCost($c, $d, $i, $free);
                        $this->vacateCard($c);
                        foreach ($this->collector as $key => [$rule, $count]) {
                            if ($rule['hard'] && $count > ($before[$key][1] ?? 0)) {
                                $found[] = 'rule:'.$rule['type'].':'.($rule['id'] ?? 'system');
                            }
                        }
                        $this->collector = null;
                    }
                }
                foreach ($found as $reason) {
                    $kind = explode(':', $reason)[0] === 'rule' ? implode(':', array_slice(explode(':', $reason), 0, 2)) : explode(':', $reason)[0];
                    $reasons[$kind] = ($reasons[$kind] ?? 0) + 1;
                }
                if (count($found) === 1 && $found[0] !== 'no_block') {
                    $single[$found[0]] ??= ['reason' => $found[0], 'day' => $d, 'lesson' => $i + 1];
                }
            }
        }
        arsort($reasons);

        return ['reasons' => $reasons, 'suggestions' => array_slice(array_values($single), 0, 6)];
    }
}
