<?php

namespace App\Http\Controllers\Attendance;

use App\Application\Attendance\Queries\ListAttendanceSessionsHandler;
use App\Application\Attendance\Queries\ListAttendanceSessionsQuery;
use App\Http\Controllers\Controller;
use App\Http\Support\AcademicYearContextResolver;
use App\Infrastructure\Persistence\Eloquent\AttendanceSessionRecord;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AttendancePageController extends Controller
{
    public function __construct(
        private readonly SchoolContext $schoolContext,
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly AcademicYearContextResolver $academicYears,
    ) {}

    public function index(Request $request, ListAttendanceSessionsHandler $handler): Response
    {
        $this->authorize('viewAny', AttendanceSessionRecord::class);

        $schoolId = $this->schoolContext->requireId();
        $user = $request->user();
        assert($user !== null);

        $requestedYear = $request->filled('academic_year_id')
            ? (int) $request->query('academic_year_id')
            : null;
        $academicYearId = $this->academicYears->resolve($requestedYear);
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(max(1, (int) $request->query('per_page', 25)), 100);

        $sessions = [
            'data' => [],
            'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => 0, 'last_page' => 1],
        ];

        if ($academicYearId !== null) {
            $result = $handler->handle(new ListAttendanceSessionsQuery(
                schoolId: $schoolId,
                academicYearId: $academicYearId,
                sectionId: $request->filled('section_id') ? (int) $request->query('section_id') : null,
                dateFrom: $request->query('date_from'),
                dateTo: $request->query('date_to'),
                status: $request->filled('status') ? (int) $request->query('status') : null,
                page: $page,
                perPage: $perPage,
            ));
            $sessions = $result->toArray();
        }

        $this->securityAudit->record(
            SecurityEventType::AttendanceDataAccess,
            'attendance.web.index',
            'allowed',
            $user,
            'attendance_sessions',
            ['page' => $page, 'academic_year_id' => $academicYearId],
        );

        return Inertia::render('attendance/index', [
            'sessions' => $sessions,
            'filters' => [
                'academic_year_id' => $academicYearId,
                'page' => $page,
                'per_page' => $perPage,
            ],
        ]);
    }
}
