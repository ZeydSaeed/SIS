<?php

namespace App\Domain\Attendance\Repositories;

/** Queued report files (attendance.report_exports): 1 queued · 2 running · 3 ready · 4 failed. */
interface AttendanceReportExportRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function create(int $schoolId, int $academicYearId, ?int $requestedBy, string $reportType, int $format, array $filters): int;

    /**
     * @return array{id: int, school_id: int, academic_year_id: int, requested_by: ?int, report_type: string, format: int, filters: array<string, mixed>, status: int, storage_key: ?string}|null
     */
    public function find(int $exportId): ?array;

    public function markRunning(int $exportId): void;

    public function markReady(int $exportId, int $rowCount, bool $truncated, string $storageKey): void;

    public function markFailed(int $exportId, string $error): void;
}
