<?php

namespace App\Http\Controllers\Organization;

use App\Application\Organization\Commands\ChangeRoomStatusCommand;
use App\Application\Organization\Commands\ChangeRoomStatusHandler;
use App\Application\Organization\Commands\ChangeRoomTypeStatusCommand;
use App\Application\Organization\Commands\ChangeRoomTypeStatusHandler;
use App\Application\Organization\Commands\SaveRoomCommand;
use App\Application\Organization\Commands\SaveRoomHandler;
use App\Application\Organization\Commands\SaveRoomTypeCommand;
use App\Application\Organization\Commands\SaveRoomTypeHandler;
use App\Application\Organization\Commands\UpdateOrganizationAppearanceCommand;
use App\Application\Organization\Commands\UpdateOrganizationAppearanceHandler;
use App\Application\Organization\Queries\GetRoomCatalogueHandler;
use App\Application\Organization\Queries\GetRoomCatalogueQuery;
use App\Application\Organization\Results\RoomCatalogueResult;
use App\Domain\Organization\Data\RoomDetails;
use App\Domain\Shared\ValueObjects\DisplayAppearance;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\ManageRoomCatalogueRequest;
use App\Http\Requests\Organization\UpdateOrganizationAppearanceRequest;
use App\Infrastructure\Persistence\Eloquent\ScheduleRecord;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «الغرف الدراسية» — the school's rooms (the source of truth the timetable reads), their managed types, and the
 * abbreviation / colour of the organization entities shown on the timetable. Delete = take out of service.
 */
final class RoomCataloguePageController extends Controller
{
    private const SORTS = ['code', 'name', 'room_number', 'type', 'capacity', 'building', 'floor', 'branch', 'status'];

    public function __construct(
        private readonly SchoolContext $schoolContext,
        private readonly SecurityAuditLoggerInterface $securityAudit,
    ) {}

    public function index(Request $request, GetRoomCatalogueHandler $handler): Response
    {
        $user = $request->user();
        abort_unless($user !== null && ($user->can('manageSchools') || $user->can('view', ScheduleRecord::class)), 403);

        $sort = in_array($request->query('sort'), self::SORTS, true) ? (string) $request->query('sort') : 'code';
        $filters = [
            'search' => mb_substr(trim((string) $request->query('search', '')), 0, 100) ?: null,
            'branch_id' => self::positiveInt($request->query('branch_id')),
            'room_type_id' => self::positiveInt($request->query('room_type_id')),
            'status' => in_array($request->query('state'), ['1', '2'], true) ? (int) $request->query('state') : null,
            'practical' => in_array($request->query('practical'), ['0', '1'], true) ? $request->query('practical') === '1' : null,
        ];
        $catalogue = $handler->handle(new GetRoomCatalogueQuery(
            schoolId: $this->schoolContext->requireId(),
            filters: $filters,
            sort: $sort,
            direction: $request->query('direction') === 'desc' ? 'desc' : 'asc',
            page: max(1, (int) $request->query('page', 1)),
            perPage: (int) $request->query('per_page', GetRoomCatalogueQuery::PER_PAGE),
        ));

        return Inertia::render('organization/rooms', $catalogue->toArray() + [
            'filters' => [
                'search' => $filters['search'] ?? '',
                'branch_id' => $filters['branch_id'],
                'room_type_id' => $filters['room_type_id'],
                'state' => $filters['status'],
                'practical' => $filters['practical'],
                'sort' => $sort,
                'direction' => $request->query('direction') === 'desc' ? 'desc' : 'asc',
            ],
            'authorization' => [
                'can_manage' => $user->can('manageSchools') || $user->can('manageConstraints', ScheduleRecord::class),
            ],
        ]);
    }

    public function storeRoom(ManageRoomCatalogueRequest $request, SaveRoomHandler $handler): RedirectResponse
    {
        return $this->respond($request, 'room', 'organization.web.rooms.store', 'flash.rooms.roomCreated', $handler->handle(new SaveRoomCommand(
            schoolId: $this->schoolContext->requireId(),
            roomId: null,
            branchId: (int) $request->validated('branch_id'),
            code: (string) $request->validated('code'),
            details: $this->details($request),
            idempotencyKey: (string) $request->header('X-Idempotency-Key'),
        )));
    }

    public function updateRoom(ManageRoomCatalogueRequest $request, int $room, SaveRoomHandler $handler): RedirectResponse
    {
        return $this->respond($request, 'room', 'organization.web.rooms.update', 'flash.rooms.roomUpdated', $handler->handle(new SaveRoomCommand(
            schoolId: $this->schoolContext->requireId(),
            roomId: $room,
            branchId: null,
            code: null,
            details: $this->details($request),
            idempotencyKey: (string) $request->header('X-Idempotency-Key'),
        )));
    }

    public function changeRoomStatus(ManageRoomCatalogueRequest $request, int $room, ChangeRoomStatusHandler $handler): RedirectResponse
    {
        $active = (int) $request->validated('active') === 1;

        return $this->respond($request, 'room', 'organization.web.rooms.status', $active ? 'flash.rooms.roomReactivated' : 'flash.rooms.roomDeactivated', $handler->handle(new ChangeRoomStatusCommand(
            schoolId: $this->schoolContext->requireId(),
            roomId: $room,
            active: $active,
            idempotencyKey: (string) $request->header('X-Idempotency-Key'),
        )));
    }

    public function storeType(ManageRoomCatalogueRequest $request, SaveRoomTypeHandler $handler): RedirectResponse
    {
        return $this->respond($request, 'room_type', 'organization.web.room-types.store', 'flash.rooms.typeCreated', $handler->handle($this->typeCommand($request, null)));
    }

    public function updateType(ManageRoomCatalogueRequest $request, int $type, SaveRoomTypeHandler $handler): RedirectResponse
    {
        return $this->respond($request, 'room_type', 'organization.web.room-types.update', 'flash.rooms.typeUpdated', $handler->handle($this->typeCommand($request, $type)));
    }

    public function changeTypeStatus(ManageRoomCatalogueRequest $request, int $type, ChangeRoomTypeStatusHandler $handler): RedirectResponse
    {
        $active = (int) $request->validated('active') === 1;

        return $this->respond($request, 'room_type', 'organization.web.room-types.status', $active ? 'flash.rooms.typeReactivated' : 'flash.rooms.typeDeactivated', $handler->handle(new ChangeRoomTypeStatusCommand(
            schoolId: $this->schoolContext->requireId(),
            typeId: $type,
            active: $active,
            idempotencyKey: (string) $request->header('X-Idempotency-Key'),
        )));
    }

    /** «الاختصار واللون» of a branch / department / room / room type (also called from the timetable). */
    public function updateAppearance(UpdateOrganizationAppearanceRequest $request, UpdateOrganizationAppearanceHandler $handler): RedirectResponse
    {
        return $this->respond($request, 'appearance', 'organization.web.appearance.update', 'flash.appearance.updated', $handler->handle(new UpdateOrganizationAppearanceCommand(
            schoolId: $this->schoolContext->requireId(),
            target: (string) $request->validated('target'),
            id: (int) $request->validated('id'),
            abbreviation: self::text($request->validated('abbreviation')),
            colorHue: self::nullableInt($request->validated('color_hue')),
            idempotencyKey: (string) $request->header('X-Idempotency-Key'),
        )));
    }

    private function details(ManageRoomCatalogueRequest $request): RoomDetails
    {
        return new RoomDetails(
            name: (string) $request->validated('name'),
            appearance: DisplayAppearance::of(self::text($request->validated('abbreviation')), self::nullableInt($request->validated('color_hue'))),
            roomNumber: self::text($request->validated('room_number')),
            roomTypeId: self::nullableInt($request->validated('room_type_id')),
            capacity: self::nullableInt($request->validated('capacity')),
            building: self::text($request->validated('building')),
            floor: self::nullableInt($request->validated('floor')),
            location: self::text($request->validated('location')),
            departmentId: self::nullableInt($request->validated('department_id')),
            supportsPractical: (bool) $request->validated('supports_practical'),
            equipment: self::text($request->validated('equipment')),
            suitableFor: self::text($request->validated('suitable_for')),
            notes: self::text($request->validated('notes')),
        );
    }

    private function typeCommand(ManageRoomCatalogueRequest $request, ?int $typeId): SaveRoomTypeCommand
    {
        $practical = $request->validated('supports_practical');

        return new SaveRoomTypeCommand(
            schoolId: $this->schoolContext->requireId(),
            typeId: $typeId,
            code: $typeId === null ? (string) $request->validated('code') : null,
            name: (string) $request->validated('name'),
            abbreviation: self::text($request->validated('abbreviation')),
            colorHue: self::nullableInt($request->validated('color_hue')),
            kind: (int) $request->validated('kind'),
            supportsPractical: $practical === null ? null : (bool) $practical,
            idempotencyKey: (string) $request->header('X-Idempotency-Key'),
        );
    }

    private function respond(Request $request, string $kind, string $auditAction, string $flash, RoomCatalogueResult $result): RedirectResponse
    {
        if ($result->failed()) {
            return redirect()->back()->withErrors([$kind => $result->errors[0] ?? 'organization.room_failed']);
        }

        $user = $request->user();
        assert($user !== null);
        $this->securityAudit->record(
            SecurityEventType::OrganizationDataModified,
            $auditAction,
            'success',
            $user,
            $kind.':'.$result->id,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return redirect()->back()->with('success', $flash);
    }

    private static function text(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private static function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private static function positiveInt(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }
}
