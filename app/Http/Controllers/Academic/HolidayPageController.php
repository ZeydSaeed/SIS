<?php

namespace App\Http\Controllers\Academic;

use App\Application\Academic\Commands\ChangeHolidayStatusCommand;
use App\Application\Academic\Commands\ChangeHolidayStatusHandler;
use App\Application\Academic\Commands\SaveHolidayCommand;
use App\Application\Academic\Commands\SaveHolidayHandler;
use App\Application\Academic\DTOs\AcademicYearDTO;
use App\Application\Academic\DTOs\HolidayDTO;
use App\Application\Academic\Queries\ListAcademicYearsHandler;
use App\Application\Academic\Queries\ListAcademicYearsQuery;
use App\Application\Academic\Queries\ListHolidaysHandler;
use App\Application\Academic\Queries\ListHolidaysQuery;
use App\Application\Academic\Results\HolidayResult;
use App\Application\Leave\Queries\GetLeaveBadgesHandler;
use App\Application\Leave\Queries\GetLeaveBadgesQuery;
use App\Domain\Academic\Repositories\HolidayRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\ManageHolidayRequest;
use App\Http\Support\LeaveRequestContext;
use App\Infrastructure\Persistence\Eloquent\ScheduleRecord;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «الاجازات» — the school calendar's holidays (the days lessons and attendance do not run). Thin: authorizes,
 * reads the list through the query handler, delegates every write to a command handler. A holiday is never
 * deleted — it is taken out of the calendar and can be put back. Global holidays (set for every school) are
 * listed but read-only.
 */
final class HolidayPageController extends Controller
{
    private const PER_PAGE = 100;

    public function __construct(
        private readonly SchoolContext $schoolContext,
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly GetLeaveBadgesHandler $badges,
        private readonly LeaveRequestContext $leave,
    ) {}

    public function index(Request $request, ListHolidaysHandler $holidays, ListAcademicYearsHandler $years): Response
    {
        $user = $request->user();
        abort_unless($user !== null && ($user->can('manageSchools') || $user->can('view', ScheduleRecord::class)), 403);

        $yearList = $years->handle(new ListAcademicYearsQuery);
        $yearId = $this->resolveYear($request->query('year'), $yearList);
        $search = mb_substr(trim((string) $request->query('search', '')), 0, 100);
        $state = in_array($request->query('state'), ['1', '2'], true) ? (int) $request->query('state') : null;
        $rawType = $request->query('type', []);
        $types = array_values(array_unique(array_filter(
            array_map('intval', is_array($rawType) ? $rawType : explode(',', (string) $rawType)),
            static fn (int $v): bool => in_array($v, [1, 2, 3], true),
        )));

        $all = $yearId === null ? [] : $holidays->handle(new ListHolidaysQuery(
            schoolId: $this->schoolContext->requireId(),
            academicYearId: $yearId,
            includeInactive: true,
        ));
        $shown = array_values(array_filter($all, static fn (HolidayDTO $h): bool => ($state === null || $h->status === $state)
            && ($types === [] || in_array($h->holidayType, $types, true))
            && ($search === '' || mb_stripos($h->name, $search) !== false)));

        $perPage = min(self::PER_PAGE, max(5, (int) $request->query('per_page', 20)));
        $lastPage = max(1, (int) ceil(count($shown) / $perPage));
        $page = min($lastPage, max(1, (int) $request->query('page', 1)));

        return Inertia::render('leave/holidays', [
            'holidays' => array_map($this->row(...), array_slice($shown, ($page - 1) * $perPage, $perPage)),
            'years' => array_map(static fn (AcademicYearDTO $y): array => [
                'id' => $y->id,
                'name' => $y->name,
                'code' => $y->code,
                'start_date' => substr($y->startDate, 0, 10),
                'end_date' => substr($y->endDate, 0, 10),
                'is_current' => $y->isCurrent,
            ], $yearList),
            'stats' => [
                'total' => count($all),
                'active' => count(array_filter($all, static fn (HolidayDTO $h): bool => $h->status === HolidayRepositoryInterface::ACTIVE)),
                'days' => $this->activeDays($all),
            ],
            'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => count($shown), 'last_page' => $lastPage],
            'filters' => ['year' => $yearId, 'search' => $search, 'state' => $state, 'type' => $types],
            'badges' => $this->badges->handle(new GetLeaveBadgesQuery($this->schoolContext->requireId(), (int) $user->id, (bool) $user->can('approveLeave'))),
            'abilities' => $this->leave->abilities($request),
            'authorization' => [
                'can_manage' => $user->can('manageSchools') || $user->can('manageConstraints', ScheduleRecord::class),
            ],
        ]);
    }

    public function store(ManageHolidayRequest $request, SaveHolidayHandler $handler): RedirectResponse
    {
        return $this->respond($request, 'academic.web.holidays.store', 'flash.holidays.created', $handler->handle(new SaveHolidayCommand(
            schoolId: $this->schoolContext->requireId(),
            holidayId: null,
            academicYearId: (int) $request->validated('academic_year_id'),
            name: (string) $request->validated('name'),
            startDate: (string) $request->validated('start_date'),
            endDate: (string) $request->validated('end_date'),
            holidayType: (int) $request->validated('holiday_type'),
            idempotencyKey: (string) $request->header('X-Idempotency-Key'),
        )));
    }

    public function update(ManageHolidayRequest $request, int $holiday, SaveHolidayHandler $handler): RedirectResponse
    {
        return $this->respond($request, 'academic.web.holidays.update', 'flash.holidays.updated', $handler->handle(new SaveHolidayCommand(
            schoolId: $this->schoolContext->requireId(),
            holidayId: $holiday,
            academicYearId: null,
            name: (string) $request->validated('name'),
            startDate: (string) $request->validated('start_date'),
            endDate: (string) $request->validated('end_date'),
            holidayType: (int) $request->validated('holiday_type'),
            idempotencyKey: (string) $request->header('X-Idempotency-Key'),
        )));
    }

    public function changeStatus(ManageHolidayRequest $request, int $holiday, ChangeHolidayStatusHandler $handler): RedirectResponse
    {
        $active = (int) $request->validated('active') === 1;

        return $this->respond($request, 'academic.web.holidays.status', $active ? 'flash.holidays.reactivated' : 'flash.holidays.deactivated', $handler->handle(new ChangeHolidayStatusCommand(
            schoolId: $this->schoolContext->requireId(),
            holidayId: $holiday,
            active: $active,
            idempotencyKey: (string) $request->header('X-Idempotency-Key'),
        )));
    }

    private function respond(Request $request, string $auditAction, string $flash, HolidayResult $result): RedirectResponse
    {
        if ($result->failed()) {
            return redirect()->back()->withErrors(['holiday' => $result->errors[0] ?? 'academic.holiday_failed']);
        }

        $user = $request->user();
        assert($user !== null);
        $this->securityAudit->record(
            SecurityEventType::AcademicDataModified,
            $auditAction,
            'success',
            $user,
            'holiday:'.$result->id,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return redirect()->back()->with('success', $flash);
    }

    /** The requested year when it exists, else the current one, else the latest. */
    private function resolveYear(mixed $requested, array $years): ?int
    {
        $ids = array_map(static fn (AcademicYearDTO $y): int => $y->id, $years);
        if (is_numeric($requested) && in_array((int) $requested, $ids, true)) {
            return (int) $requested;
        }
        foreach ($years as $year) {
            if ($year->isCurrent) {
                return $year->id;
            }
        }

        return $ids === [] ? null : max($ids);
    }

    /** Calendar days covered by the active holidays (overlapping ranges counted once). */
    private function activeDays(array $holidays): int
    {
        $days = [];
        foreach ($holidays as $holiday) {
            if ($holiday->status !== HolidayRepositoryInterface::ACTIVE) {
                continue;
            }
            for ($at = strtotime(substr($holiday->startDate, 0, 10)), $end = strtotime(substr($holiday->endDate, 0, 10)); $at !== false && $end !== false && $at <= $end; $at += 86400) {
                $days[date('Y-m-d', $at)] = true;
            }
        }

        return count($days);
    }

    /** @return array<string, mixed> */
    private function row(HolidayDTO $holiday): array
    {
        $start = substr($holiday->startDate, 0, 10);
        $end = substr($holiday->endDate, 0, 10);

        return [
            'id' => $holiday->id,
            'academic_year_id' => $holiday->academicYearId,
            'name' => $holiday->name,
            'start_date' => $start,
            'end_date' => $end,
            'days' => (int) round((strtotime($end) - strtotime($start)) / 86400) + 1,
            'holiday_type' => $holiday->holidayType,
            'global' => $holiday->schoolId === null,
            'status' => $holiday->status,
        ];
    }
}
