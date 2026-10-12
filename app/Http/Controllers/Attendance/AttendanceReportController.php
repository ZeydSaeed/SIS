<?php

namespace App\Http\Controllers\Attendance;

use App\Application\Attendance\Commands\RequestAttendanceReportExportCommand;
use App\Application\Attendance\Commands\RequestAttendanceReportExportHandler;
use App\Application\Attendance\Contracts\AttendanceReportReadInterface;
use App\Application\Attendance\Contracts\AttendanceWorkspaceReadInterface;
use App\Application\Attendance\Queries\GetAttendanceReportHandler;
use App\Application\Attendance\Queries\GetAttendanceReportQuery;
use App\Domain\Attendance\Exceptions\AttendanceRuleViolation;
use App\Domain\Attendance\Repositories\AttendanceReportExportRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\RequestAttendanceReportExportRequest;
use App\Http\Support\AttendanceFilterInput;
use App\Http\Support\AttendanceRequestContext;
use App\Infrastructure\Persistence\Eloquent\AttendanceSessionRecord;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * «تقارير الحضور»: server-side aggregation and pagination. Exports use the same query and filters: immediate CSV
 * (UTF-8 with BOM, opens in Excel) up to the cap, or a queued CSV / PDF file built in the background. School-scoped
 * and audited.
 */
final class AttendanceReportController extends Controller
{
    private const LIST_FILTERS = ['class_id', 'section_id', 'subject_id', 'teacher_id', 'student_id', 'status', 'lesson_type'];

    public function __construct(
        private readonly AttendanceRequestContext $context,
        private readonly SecurityAuditLoggerInterface $securityAudit,
    ) {}

    public function index(Request $request, GetAttendanceReportHandler $handler, AttendanceWorkspaceReadInterface $read): Response
    {
        $this->authorize('viewReports', AttendanceSessionRecord::class);
        $schoolId = $this->context->schoolId();
        $yearId = $this->context->academicYearId($request);
        [$type, $filters] = $this->criteria($request);
        $page = max(1, (int) $request->query('page', 1));
        $report = $handler->handle(new GetAttendanceReportQuery($schoolId, $yearId, $type, $filters, $page, (int) config('attendance.per_page.reports', 50)));

        return Inertia::render('attendance/reports', [
            'report' => $report->toArray(),
            'filters' => $filters + ['type' => $type, 'page' => $page, 'academic_year_id' => $yearId],
            'types' => AttendanceReportReadInterface::TYPES,
            'lookups' => $read->lookups($schoolId, $yearId),
            'exports' => $read->exports($schoolId, (int) $request->user()?->id, 10),
            'abilities' => $this->context->abilities($request),
        ]);
    }

    public function export(Request $request, GetAttendanceReportHandler $handler): StreamedResponse
    {
        $this->authorize('viewReports', AttendanceSessionRecord::class);
        [$type, $filters] = $this->criteria($request);
        $report = $handler->handle(new GetAttendanceReportQuery(
            $this->context->schoolId(), $this->context->academicYearId($request), $type, $filters, 1, 1, export: true,
        ))->toArray();
        $this->securityAudit->record(SecurityEventType::AttendanceDataAccess, 'attendance.web.report.export', 'exported', $request->user(), "attendance_report:{$type}", [
            'rows' => count($report['rows']), 'filters' => $filters, 'truncated' => $report['truncated'],
        ]);

        return response()->streamDownload(static function () use ($report, $filters, $type): void {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['تقرير الحضور', $type, 'تاريخ الاستخراج', $report['generated_at']]);
            fputcsv($out, ['المعايير', json_encode($filters, JSON_UNESCAPED_UNICODE)]);
            fputcsv($out, ['طريقة الاحتساب', $report['formula']]);
            if ($report['truncated']) {
                fputcsv($out, ['تنبيه', 'تجاوز التصدير الحد الأقصى؛ ضيّق المعايير للحصول على كامل البيانات.']);
            }
            fputcsv($out, array_keys($report['rows'][0] ?? []));
            foreach ($report['rows'] as $row) {
                fputcsv($out, array_map(static fn ($v): string => is_scalar($v) || $v === null ? (string) $v : (string) json_encode($v, JSON_UNESCAPED_UNICODE), array_values($row)));
            }
            fclose($out);
        }, "attendance-{$type}-".now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Queued CSV (1) / PDF (2): built in the background, listed on the reports page, downloaded when ready. */
    public function requestExport(RequestAttendanceReportExportRequest $request, RequestAttendanceReportExportHandler $handler): RedirectResponse
    {
        [, $filters] = $this->criteria($request);
        $result = $handler->handle(new RequestAttendanceReportExportCommand(
            $this->context->schoolId(), $this->context->academicYearId($request), (string) $request->validated('type'), $filters,
            (int) $request->validated('format'), (int) $request->user()?->id, $request->idempotencyKey('report-export'),
        ));
        $this->securityAudit->record(SecurityEventType::AttendanceDataAccess, 'attendance.web.report.export_queued', 'queued', $request->user(), 'attendance_report_export:'.$result->get('export_id'), [
            'type' => $request->validated('type'), 'format' => $request->validated('format'), 'filters' => $filters,
        ]);

        return back()->with('success', 'flash.attendance.exportQueued');
    }

    public function download(Request $request, int $export, AttendanceReportExportRepositoryInterface $exports): StreamedResponse
    {
        $this->authorize('viewReports', AttendanceSessionRecord::class);
        $row = $exports->find($export);
        // Same school, own request, ready file — anything else is «not found».
        if ($row === null || $row['school_id'] !== $this->context->schoolId() || $row['requested_by'] !== (int) $request->user()?->id
            || $row['status'] !== 3 || $row['storage_key'] === null || ! Storage::disk('local')->exists($row['storage_key'])) {
            throw AttendanceRuleViolation::exportNotFound();
        }
        $this->securityAudit->record(SecurityEventType::AttendanceDataAccess, 'attendance.web.report.download', 'downloaded', $request->user(), "attendance_report_export:{$export}");
        $extension = $row['format'] === 2 ? 'pdf' : 'csv';

        return Storage::disk('local')->download($row['storage_key'], "attendance-{$row['report_type']}-{$export}.{$extension}", [
            'Content-Type' => $row['format'] === 2 ? 'application/pdf' : 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function criteria(Request $request): array
    {
        $type = (string) $request->input('type', 'students');
        $type = in_array($type, AttendanceReportReadInterface::TYPES, true) ? $type : 'students';
        $source = $request->has('filters') && is_array($request->input('filters')) ? Request::create('/', 'GET', (array) $request->input('filters')) : $request;
        $filters = AttendanceFilterInput::from($source, self::LIST_FILTERS, ['date_from', 'date_to'], ['approved_only']);
        $filters['date_from'] ??= now()->subDays(29)->toDateString();
        $filters['date_to'] ??= now()->toDateString();

        return [$type, $filters];
    }
}
