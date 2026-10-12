<?php

namespace App\Application\Attendance\Contracts;

/** Hands a queued export to the background queue (never generated inside the HTTP request). */
interface ReportExportDispatcherInterface
{
    public function dispatch(int $exportId, int $schoolId): void;
}
