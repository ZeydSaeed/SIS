<?php

namespace App\Application\Attendance\Commands;

use App\Application\Contracts\Command;

/** Queue a report file (1 = CSV for Excel, 2 = PDF) with exactly the filters shown on screen. */
final readonly class RequestAttendanceReportExportCommand implements Command
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public string $reportType,
        public array $filters,
        public int $format,
        public int $requestedBy,
        public string $idempotencyKey,
    ) {}
}
