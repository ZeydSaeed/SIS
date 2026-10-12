<?php

namespace App\Infrastructure\Persistence\Attendance;

use App\Domain\Attendance\Repositories\AttendanceReportExportRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class EloquentAttendanceReportExportRepository implements AttendanceReportExportRepositoryInterface
{
    public function create(int $schoolId, int $academicYearId, ?int $requestedBy, string $reportType, int $format, array $filters): int
    {
        return (int) DB::table('attendance.report_exports')->insertGetId([
            'school_id' => $schoolId,
            'academic_year_id' => $academicYearId,
            'requested_by' => $requestedBy,
            'report_type' => $reportType,
            'format' => $format,
            'filters' => json_encode($filters, JSON_UNESCAPED_UNICODE),
            'status' => 1,
            'created_at' => now(),
        ]);
    }

    public function find(int $exportId): ?array
    {
        $row = DB::table('attendance.report_exports')->where('id', $exportId)
            ->first(['id', 'school_id', 'academic_year_id', 'requested_by', 'report_type', 'format', 'filters', 'status', 'storage_key']);
        if ($row === null) {
            return null;
        }

        return [
            'id' => (int) $row->id,
            'school_id' => (int) $row->school_id,
            'academic_year_id' => (int) $row->academic_year_id,
            'requested_by' => $row->requested_by !== null ? (int) $row->requested_by : null,
            'report_type' => (string) $row->report_type,
            'format' => (int) $row->format,
            'filters' => (array) json_decode((string) $row->filters, true),
            'status' => (int) $row->status,
            'storage_key' => $row->storage_key !== null ? (string) $row->storage_key : null,
        ];
    }

    public function markRunning(int $exportId): void
    {
        DB::table('attendance.report_exports')->where('id', $exportId)->update(['status' => 2]);
    }

    public function markReady(int $exportId, int $rowCount, bool $truncated, string $storageKey): void
    {
        DB::table('attendance.report_exports')->where('id', $exportId)->update([
            'status' => 3, 'row_count' => $rowCount, 'truncated' => $truncated, 'storage_key' => $storageKey, 'finished_at' => now(),
        ]);
    }

    public function markFailed(int $exportId, string $error): void
    {
        DB::table('attendance.report_exports')->where('id', $exportId)->update(['status' => 4, 'error' => mb_substr($error, 0, 1000), 'finished_at' => now()]);
    }
}
