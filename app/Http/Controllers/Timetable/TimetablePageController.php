<?php

namespace App\Http\Controllers\Timetable;

use App\Application\Timetable\DTOs\ScheduleDTO;
use App\Application\Timetable\Queries\ListSchedulesHandler;
use App\Application\Timetable\Queries\ListSchedulesQuery;
use App\Http\Controllers\Controller;
use App\Http\Support\AcademicYearContextResolver;
use App\Infrastructure\Persistence\Eloquent\ScheduleRecord;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TimetablePageController extends Controller
{
    public function __construct(
        private readonly SchoolContext $schoolContext,
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly AcademicYearContextResolver $academicYears,
    ) {}

    public function index(Request $request, ListSchedulesHandler $handler): Response
    {
        $this->authorize('view', ScheduleRecord::class);

        $schoolId = $this->schoolContext->requireId();
        $user = $request->user();
        assert($user !== null);

        $requestedYear = $request->filled('academic_year_id')
            ? (int) $request->query('academic_year_id')
            : null;
        $academicYearId = $this->academicYears->resolve($requestedYear);
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(max(1, (int) $request->query('per_page', 50)), 100);

        $schedules = [
            'data' => [],
            'meta' => [
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => 0,
                    'total_pages' => 1,
                ],
            ],
        ];

        if ($academicYearId !== null) {
            $result = $handler->handle(new ListSchedulesQuery(
                schoolId: $schoolId,
                academicYearId: $academicYearId,
                sectionId: $request->filled('section_id') ? (int) $request->query('section_id') : null,
                lifecycleStatus: $request->filled('lifecycle_status') ? (int) $request->query('lifecycle_status') : null,
                page: $page,
                perPage: $perPage,
            ));
            $schedules = [
                'data' => array_map(static fn (ScheduleDTO $s): array => [
                    'id' => $s->id,
                    'section_id' => $s->sectionId,
                    'academic_year_id' => $s->academicYearId,
                    'day_of_week' => $s->dayOfWeek,
                    'period_id' => $s->periodId,
                    'subject_id' => $s->subjectId,
                    'teacher_id' => $s->teacherId,
                    'room_id' => $s->roomId,
                    'lifecycle_status' => $s->lifecycleStatus,
                ], $result->items),
                'meta' => ['pagination' => $result->pagination],
            ];
        }

        $this->securityAudit->record(
            SecurityEventType::TimetableDataAccess,
            'timetable.web.index',
            'viewed',
            $user,
            'schedules',
            ['academic_year_id' => $academicYearId],
        );

        return Inertia::render('timetable/index', [
            'schedules' => $schedules,
            'filters' => [
                'academic_year_id' => $academicYearId,
                'page' => $page,
                'per_page' => $perPage,
            ],
        ]);
    }
}
