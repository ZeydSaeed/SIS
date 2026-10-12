<?php

namespace App\Infrastructure\Attendance;

use App\Application\Attendance\Contracts\ReportExportDispatcherInterface;
use App\Infrastructure\Jobs\GenerateAttendanceReportExportJob;

final class QueueReportExportDispatcher implements ReportExportDispatcherInterface
{
    public function dispatch(int $exportId, int $schoolId): void
    {
        // After commit: the job must see the export row.
        GenerateAttendanceReportExportJob::dispatch($exportId, $schoolId)->afterCommit();
    }
}
