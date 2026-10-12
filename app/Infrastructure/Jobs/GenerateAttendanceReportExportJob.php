<?php

namespace App\Infrastructure\Jobs;

use App\Application\Attendance\Queries\GetAttendanceReportHandler;
use App\Application\Attendance\Queries\GetAttendanceReportQuery;
use App\Domain\Attendance\Repositories\AttendanceReportExportRepositoryInterface;
use App\Infrastructure\Attendance\AttendanceReportFileWriter;
use App\Security\Context\SchoolContextScope;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/** Builds a queued attendance report file under the requesting school's tenant context. */
final class GenerateAttendanceReportExportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public readonly int $exportId,
        public readonly int $schoolId,
    ) {}

    public function handle(AttendanceReportExportRepositoryInterface $exports, GetAttendanceReportHandler $reports, AttendanceReportFileWriter $writer, SchoolContextScope $scope): void
    {
        $scope->run($this->schoolId, function () use ($exports, $reports, $writer): void {
            $export = $exports->find($this->exportId);
            if ($export === null || $export['school_id'] !== $this->schoolId || $export['status'] !== 1) {
                return;
            }
            $exports->markRunning($this->exportId);
            try {
                $report = $reports->handle(new GetAttendanceReportQuery(
                    $export['school_id'], $export['academic_year_id'], $export['report_type'], $export['filters'], 1, 1, export: true,
                ))->toArray();
                $pdf = $export['format'] === 2;
                $key = sprintf('attendance-exports/%d/%d.%s', $this->schoolId, $this->exportId, $pdf ? 'pdf' : 'csv');
                Storage::disk('local')->put($key, $pdf ? $writer->pdf($report) : $writer->csv($report));
                $exports->markReady($this->exportId, count($report['rows']), (bool) $report['truncated'], $key);
            } catch (\Throwable $e) {
                $exports->markFailed($this->exportId, $e->getMessage());
            }
        });
    }
}
