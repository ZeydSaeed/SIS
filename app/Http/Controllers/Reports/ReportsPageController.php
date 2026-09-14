<?php

namespace App\Http\Controllers\Reports;

use App\Application\Attendance\DTOs\DailySectionSummaryDTO;
use App\Application\Attendance\Queries\GetDailySectionSummaryHandler;
use App\Application\Attendance\Queries\GetDailySectionSummaryQuery;
use App\Application\Enrollment\Queries\ListEnrollmentsHandler;
use App\Application\Enrollment\Queries\ListEnrollmentsQuery;
use App\Http\Controllers\Controller;
use App\Http\Support\AcademicYearContextResolver;
use App\Infrastructure\Persistence\Eloquent\AttendanceSessionRecord;
use App\Infrastructure\Persistence\Eloquent\EnrollmentRecord;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ReportsPageController extends Controller
{
    public function __construct(
        private readonly SchoolContext $schoolContext,
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly AcademicYearContextResolver $academicYears,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        assert($user !== null);

        $this->securityAudit->record(
            SecurityEventType::AttendanceDataAccess,
            'reports.web.index',
            'allowed',
            $user,
            'reports_hub',
        );

        return Inertia::render('reports/index');
    }

    public function attendanceDaily(Request $request, GetDailySectionSummaryHandler $handler): Response
    {
        $this->authorize('viewAny', AttendanceSessionRecord::class);

        $schoolId = $this->schoolContext->requireId();
        $user = $request->user();
        assert($user !== null);

        $sectionId = $request->filled('section_id') ? (int) $request->query('section_id') : null;
        $date = $request->query('date');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $rows = [];
        if (
            $sectionId !== null
            && $sectionId > 0
            && (
                (is_string($date) && $date !== '')
                || (is_string($dateFrom) && $dateFrom !== '')
            )
        ) {
            $items = $handler->handle(new GetDailySectionSummaryQuery(
                schoolId: $schoolId,
                sectionId: $sectionId,
                date: is_string($date) && $date !== '' ? $date : null,
                dateFrom: is_string($dateFrom) && $dateFrom !== '' ? $dateFrom : null,
                dateTo: is_string($dateTo) && $dateTo !== '' ? $dateTo : null,
            ));
            $rows = array_map(
                static fn (DailySectionSummaryDTO $dto): array => $dto->toArray(),
                $items,
            );
        }

        $this->securityAudit->record(
            SecurityEventType::AttendanceDataAccess,
            'reports.web.attendance_daily',
            'allowed',
            $user,
            'daily_section_summary',
            ['section_id' => $sectionId, 'count' => count($rows)],
        );

        return Inertia::render('reports/attendance-daily', [
            'summary' => ['data' => $rows],
            'filters' => [
                'section_id' => $sectionId,
                'date' => is_string($date) ? $date : null,
                'date_from' => is_string($dateFrom) ? $dateFrom : null,
                'date_to' => is_string($dateTo) ? $dateTo : null,
            ],
        ]);
    }

    public function enrollmentRoster(Request $request, ListEnrollmentsHandler $handler): Response
    {
        $this->authorize('viewAny', EnrollmentRecord::class);

        $schoolId = $this->schoolContext->requireId();
        $user = $request->user();
        assert($user !== null);

        $requestedYear = $request->filled('academic_year_id')
            ? (int) $request->query('academic_year_id')
            : null;
        $academicYearId = $this->academicYears->resolve($requestedYear);
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(max(1, (int) $request->query('per_page', 25)), 100);

        $enrollments = [
            'data' => [],
            'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => 0, 'last_page' => 1],
        ];

        if ($academicYearId !== null) {
            $result = $handler->handle(new ListEnrollmentsQuery(
                schoolId: $schoolId,
                academicYearId: $academicYearId,
                page: $page,
                perPage: $perPage,
            ));
            $enrollments = $result->toArray();
        }

        $this->securityAudit->record(
            SecurityEventType::EnrollmentDataAccess,
            'reports.web.enrollment_roster',
            'allowed',
            $user,
            'enrollment_roster',
            ['page' => $page, 'academic_year_id' => $academicYearId],
        );

        return Inertia::render('reports/enrollment-roster', [
            'enrollments' => $enrollments,
            'filters' => [
                'academic_year_id' => $academicYearId,
                'page' => $page,
                'per_page' => $perPage,
            ],
        ]);
    }
}
