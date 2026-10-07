<?php

namespace App\Domain\Timetable\Solver;

/** Lets a long run report progress and be cancelled (a queued generation run polls its row). */
interface SolverProgress
{
    public function report(int $placed, int $total, int $hardViolations, int $softPenalty): void;

    public function shouldStop(): bool;
}
