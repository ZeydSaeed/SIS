<?php

namespace App\Http\Controllers\Teachers;

use App\Application\Teachers\DTOs\TeacherDTO;
use App\Application\Teachers\Queries\ListTeachersHandler;
use App\Application\Teachers\Queries\ListTeachersQuery;
use App\Http\Controllers\Controller;
use App\Http\Support\AcademicYearContextResolver;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TeacherPageController extends Controller
{
    public function __construct(
        private readonly SchoolContext $schoolContext,
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly AcademicYearContextResolver $academicYears,
    ) {}

    public function index(Request $request, ListTeachersHandler $handler): Response
    {
        $user = $request->user();
        assert($user !== null);

        if (! $user->can('viewTeachers')) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        $schoolId = $this->schoolContext->requireId();
        $requestedYear = $request->filled('academic_year_id')
            ? (int) $request->query('academic_year_id')
            : null;
        $academicYearId = $this->academicYears->resolve($requestedYear);
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(max(1, (int) $request->query('per_page', 25)), 100);

        $payload = [
            'data' => [],
            'meta' => [
                'total' => 0,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => 1,
            ],
        ];

        if ($academicYearId !== null) {
            $result = $handler->handle(new ListTeachersQuery(
                schoolId: $schoolId,
                academicYearId: $academicYearId,
                page: $page,
                perPage: $perPage,
            ));
            $payload = [
                'data' => array_map(static fn (TeacherDTO $t): array => [
                    'id' => $t->id,
                    'employee_code' => $t->employeeCode,
                    'full_name' => $t->fullName,
                    'specialization_field' => $t->specializationField,
                    'status' => $t->status,
                    'is_primary' => $t->isPrimary,
                    'academic_year_id' => $t->academicYearId,
                ], $result->items),
                'meta' => [
                    'total' => $result->total,
                    'page' => $result->page,
                    'per_page' => $result->perPage,
                    'last_page' => max(1, (int) ceil($result->total / max(1, $result->perPage))),
                ],
            ];
        }

        $this->securityAudit->record(
            SecurityEventType::TeacherDataAccess,
            'teachers.web.index',
            'viewed',
            $user,
            'school:'.$schoolId,
            ['academic_year_id' => $academicYearId, 'total' => $payload['meta']['total']],
        );

        return Inertia::render('teachers/index', [
            'teachers' => $payload,
            'filters' => [
                'academic_year_id' => $academicYearId,
                'page' => $page,
                'per_page' => $perPage,
            ],
        ]);
    }
}
