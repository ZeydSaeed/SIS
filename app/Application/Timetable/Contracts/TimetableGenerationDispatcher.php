<?php

namespace App\Application\Timetable\Contracts;

/** Runs a queued generation off the HTTP request (spec §68) — the queue in production, inline under `sync`. */
interface TimetableGenerationDispatcher
{
    public function dispatch(int $schoolId, int $runId): void;
}
