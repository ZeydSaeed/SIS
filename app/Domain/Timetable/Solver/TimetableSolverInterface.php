<?php

namespace App\Domain\Timetable\Solver;

/**
 * The replaceable solver port (decision D5). The engine compiles SIS data into a {@see SolverProblem};
 * any implementation — the PHP heuristic today, CP-SAT / MIP / a cloud service tomorrow (behind an
 * Infrastructure adapter and an ADR) — returns a {@see SolverResult}. Verification and persistence stay
 * outside the solver.
 */
interface TimetableSolverInterface
{
    public function name(): string;

    public function solve(SolverProblem $problem, SolverOptions $options, ?SolverProgress $progress = null): SolverResult;
}
