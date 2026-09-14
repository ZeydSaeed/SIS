<?php

namespace App\Http\Controllers\Enrollment;

use App\Application\Enrollment\Queries\ListEnrollmentsHandler;
use App\Application\Enrollment\Queries\ListEnrollmentsQuery;
use App\Http\Controllers\Controller;
use App\Http\Support\AcademicYearContextResolver;
use App\Infrastructure\Persistence\Eloquent\EnrollmentRecord;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class EnrollmentPageController extends Controller
{
    public function __construct(
        private readonly SchoolContext $schoolContext,
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly AcademicYearContextResolver $academicYears,
    ) {}

    public function index(Request $request, ListEnrollmentsHandler $handler): Response
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

        $result = $handler->handle(new ListEnrollmentsQuery(
            schoolId: $schoolId,
            academicYearId: $academicYearId,
            page: $page,
            perPage: $perPage,
        ));

        $this->securityAudit->record(
            SecurityEventType::EnrollmentDataAccess,
            'enrollments.web.index',
            'allowed',
            $user,
            'enrollments',
            ['page' => $page, 'per_page' => $perPage, 'academic_year_id' => $academicYearId],
        );

        return Inertia::render('enrollments/index', [
            'enrollments' => $result->toArray(),
            'filters' => [
                'academic_year_id' => $academicYearId,
                'page' => $page,
                'per_page' => $perPage,
            ],
        ]);
    }
}
