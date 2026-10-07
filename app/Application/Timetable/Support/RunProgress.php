<?php

namespace App\Application\Timetable\Support;

use App\Domain\Timetable\Repositories\GenerationRunRepositoryInterface;
use App\Domain\Timetable\Solver\SolverProgress;

/** Writes a run's progress at most once a second and polls its cancel flag at most every two seconds. */
final class RunProgress implements SolverProgress
{
    private float $lastReport = 0.0;

    private float $lastPoll = 0.0;

    private bool $stop = false;

    public function __construct(
        private readonly GenerationRunRepositoryInterface $runs,
        private readonly int $schoolId,
        private readonly int $runId,
    ) {}

    public function report(int $placed, int $total, int $hardViolations, int $softPenalty): void
    {
        $now = microtime(true);
        if ($now - $this->lastReport < 1.0 && $placed < $total) {
            return;
        }
        $this->lastReport = $now;
        $this->runs->reportProgress($this->schoolId, $this->runId, [
            'placed' => $placed, 'total' => $total, 'hard_violations' => $hardViolations, 'soft_penalty' => $softPenalty,
            'at' => (new \DateTimeImmutable)->format(\DateTimeInterface::ATOM),
        ]);
    }

    public function shouldStop(): bool
    {
        $now = microtime(true);
        if (! $this->stop && $now - $this->lastPoll >= 2.0) {
            $this->lastPoll = $now;
            $this->stop = $this->runs->cancelRequested($this->schoolId, $this->runId);
        }

        return $this->stop;
    }
}
