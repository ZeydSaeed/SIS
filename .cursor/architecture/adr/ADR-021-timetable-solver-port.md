# ADR-021: Timetable Solver Port and PHP Heuristic Solver

## Status
Accepted — 2026-10-07 (decision D5, human-approved)

## Context
The timetable engine must generate school-wide timetables with hard and soft constraints, groups, joined
classes, co-teaching, rooms / workshops, availability, multi-week cycles and locked lessons, and explain
failures. Mature external solvers (OR-Tools CP-SAT, MIP) would add a new runtime (Python / native binaries),
which the Constitution forbids without an ADR. Schools are bounded (≈ 30–90 sections, ≤ 2 500 blocks).

## Decision
1. **Port.** `App\Domain\Timetable\Solver\TimetableSolverInterface::solve(SolverProblem, SolverOptions, ?SolverProgress): SolverResult`.
   The Domain owns the problem / result model; any solver is an adapter. Bound in `ArchitectureServiceProvider`.
2. **Compiler.** `ConstraintCompiler` turns the SIS projection (`TimetableBoard`) into a `SolverProblem`:
   blocks from activities (distribution / block length), implicit activities from requirements, fixed occupancy
   (locked / out-of-scope lessons), availability maps, rules resolved by precedence, mode-dependent hardness.
3. **First adapter: `HeuristicTimetableSolver`** (pure PHP, deterministic for a seed + iteration cap):
   most-constrained-first construction, ejection chains (depth ≤ 3, undo journal), seeded simulated annealing
   (moves / swaps) that never breaks a hard rule nor unplaces a block, explainable diagnosis of every unplaced
   block (rejection reasons per position + single-blocker hints = minimal relaxation suggestions).
4. **Runs off HTTP.** `QueueTimetableGenerationCommand` → `ExecuteTimetableGenerationJob` (queue; inline under
   `sync`), progress / cancel through the run row, results stored for human review; applying is a separate command.
5. **Replacement path.** A CP-SAT / MIP / cloud solver becomes an Infrastructure adapter of the same port —
   with its own ADR for the runtime it brings. No Domain or Application change is needed.

## Consequences
- Measured (stress script, Windows dev box, PHP 8.4): 30 sections / 840 blocks ≈ 6–9 s, 60 / 1 680 ≈ 10 s,
  90 / 2 520 ≈ 18 s — all placed, 0 hard violations, no section gaps. Over-constrained input is reported, not hidden.
- The heuristic gives good, not proven-optimal, timetables; the quality score makes the difference visible.
- Same seed + same input + iteration-bound run ⇒ same result; a time-bound run may stop earlier on a slower machine.
