<?php

namespace App\Http\Controllers\Api;

use App\Application\Vocational\Commands\CreateSpecializationCommand;
use App\Application\Vocational\Commands\CreateSpecializationHandler;
use App\Application\Vocational\Commands\CreateTrackCommand;
use App\Application\Vocational\Commands\CreateTrackHandler;
use App\Application\Vocational\Commands\CreateWorkshopCommand;
use App\Application\Vocational\Commands\CreateWorkshopEquipmentCommand;
use App\Application\Vocational\Commands\CreateWorkshopEquipmentHandler;
use App\Application\Vocational\Commands\CreateWorkshopHandler;
use App\Application\Vocational\Commands\DeactivateWorkshopCommand;
use App\Application\Vocational\Commands\DeactivateWorkshopEquipmentCommand;
use App\Application\Vocational\Commands\DeactivateWorkshopEquipmentHandler;
use App\Application\Vocational\Commands\DeactivateWorkshopHandler;
use App\Application\Vocational\Commands\DeactivateSpecializationCommand;
use App\Application\Vocational\Commands\DeactivateSpecializationHandler;
use App\Application\Vocational\Commands\DeactivateSpecializationSubjectCommand;
use App\Application\Vocational\Commands\DeactivateSpecializationSubjectHandler;
use App\Application\Vocational\Commands\DeactivateTrackCommand;
use App\Application\Vocational\Commands\DeactivateTrackHandler;
use App\Application\Vocational\Commands\LinkSpecializationSubjectCommand;
use App\Application\Vocational\Commands\LinkSpecializationSubjectHandler;
use App\Application\Vocational\Commands\UpdateSpecializationCommand;
use App\Application\Vocational\Commands\UpdateSpecializationHandler;
use App\Application\Vocational\Commands\UpdateTrackCommand;
use App\Application\Vocational\Commands\UpdateTrackHandler;
use App\Application\Vocational\DTOs\SpecializationDTO;
use App\Application\Vocational\DTOs\WorkshopDTO;
use App\Application\Vocational\DTOs\WorkshopEquipmentDTO;
use App\Application\Vocational\Queries\GetSpecializationHandler;
use App\Application\Vocational\Queries\GetSpecializationQuery;
use App\Application\Vocational\Queries\ListSpecializationsHandler;
use App\Application\Vocational\Queries\ListSpecializationsQuery;
use App\Application\Vocational\Queries\ListWorkshopEquipmentHandler;
use App\Application\Vocational\Queries\ListWorkshopEquipmentQuery;
use App\Application\Vocational\Queries\ListWorkshopsHandler;
use App\Application\Vocational\Queries\ListWorkshopsQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vocational\CreateSpecializationRequest;
use App\Http\Requests\Vocational\CreateTrackRequest;
use App\Http\Requests\Vocational\CreateWorkshopEquipmentRequest;
use App\Http\Requests\Vocational\CreateWorkshopRequest;
use App\Http\Requests\Vocational\DeactivateSpecializationRequest;
use App\Http\Requests\Vocational\DeactivateSpecializationSubjectRequest;
use App\Http\Requests\Vocational\DeactivateTrackRequest;
use App\Http\Requests\Vocational\DeactivateWorkshopEquipmentRequest;
use App\Http\Requests\Vocational\DeactivateWorkshopRequest;
use App\Http\Requests\Vocational\LinkSpecializationSubjectRequest;
use App\Http\Requests\Vocational\ListSpecializationsRequest;
use App\Http\Requests\Vocational\ListWorkshopEquipmentRequest;
use App\Http\Requests\Vocational\ListWorkshopsRequest;
use App\Http\Requests\Vocational\ShowSpecializationRequest;
use App\Http\Requests\Vocational\UpdateSpecializationRequest;
use App\Http\Requests\Vocational\UpdateTrackRequest;
use App\Intelligence\Support\CorrelationContext;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Http\JsonResponse;

class VocationalController extends Controller
{
    public function __construct(
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly SchoolContext $schoolContext,
    ) {}

    public function indexSpecializations(
        ListSpecializationsRequest $request,
        ListSpecializationsHandler $handler,
    ): JsonResponse {
        $page = $handler->handle(new ListSpecializationsQuery(
            schoolId: $this->schoolContext->requireId(),
            status: $request->validated('status') !== null
                ? (int) $request->validated('status')
                : null,
            page: (int) ($request->validated('page') ?? 1),
            perPage: (int) ($request->validated('per_page') ?? 50),
        ));

        $this->securityAudit->record(
            SecurityEventType::VocationalDataAccess,
            'vocational.specializations.index',
            'viewed',
            $request->user(),
            'specializations',
            [],
        );

        return response()->json([
            'data' => array_map(fn (SpecializationDTO $s): array => $this->specializationPayload($s), $page->items),
            'meta' => [
                'pagination' => $page->pagination,
                'correlation_id' => CorrelationContext::id(),
            ],
        ]);
    }

    public function showSpecialization(
        ShowSpecializationRequest $request,
        int $specialization,
        GetSpecializationHandler $handler,
    ): JsonResponse {
        $dto = $handler->handle(new GetSpecializationQuery(
            schoolId: $this->schoolContext->requireId(),
            specializationId: $specialization,
        ));

        if ($dto === null) {
            return response()->json([
                'message' => 'Specialization not found.',
                'error_code' => 'vocational.specialization_not_found',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 404);
        }

        $this->securityAudit->record(
            SecurityEventType::VocationalDataAccess,
            'vocational.specializations.show',
            'viewed',
            $request->user(),
            'specialization:'.$dto->id,
            [],
        );

        return response()->json([
            'data' => $this->specializationPayload($dto),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function storeSpecialization(
        CreateSpecializationRequest $request,
        CreateSpecializationHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new CreateSpecializationCommand(
            schoolId: $this->schoolContext->requireId(),
            code: (string) $request->validated('code'),
            name: (string) $request->validated('name'),
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
            description: $request->validated('description'),
            correlationId: CorrelationContext::id(),
        ));

        $this->audit($request->user(), 'vocational.specializations.store', 'created', 'specialization:'.($result->specializationId ?? 'unknown'));

        return $this->idResponse($result->specializationId, $result->fromIdempotencyCache, $result->fromIdempotencyCache ? 200 : 201);
    }

    public function updateSpecialization(
        UpdateSpecializationRequest $request,
        int $specialization,
        UpdateSpecializationHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new UpdateSpecializationCommand(
            schoolId: $this->schoolContext->requireId(),
            specializationId: $specialization,
            code: (string) $request->validated('code'),
            name: (string) $request->validated('name'),
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
            description: $request->validated('description'),
            correlationId: CorrelationContext::id(),
        ));

        $this->audit($request->user(), 'vocational.specializations.update', 'updated', 'specialization:'.($result->specializationId ?? 'unknown'));

        return $this->idResponse($result->specializationId, $result->fromIdempotencyCache);
    }

    public function deactivateSpecialization(
        DeactivateSpecializationRequest $request,
        int $specialization,
        DeactivateSpecializationHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new DeactivateSpecializationCommand(
            schoolId: $this->schoolContext->requireId(),
            specializationId: $specialization,
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
            correlationId: CorrelationContext::id(),
        ));

        $this->audit($request->user(), 'vocational.specializations.deactivate', 'deactivated', 'specialization:'.($result->specializationId ?? 'unknown'));

        return $this->idResponse($result->specializationId, $result->fromIdempotencyCache);
    }

    public function storeTrack(
        CreateTrackRequest $request,
        int $specialization,
        CreateTrackHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new CreateTrackCommand(
            schoolId: $this->schoolContext->requireId(),
            specializationId: $specialization,
            code: (string) $request->validated('code'),
            name: (string) $request->validated('name'),
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
            correlationId: CorrelationContext::id(),
        ));

        $this->audit($request->user(), 'vocational.tracks.store', 'created', 'track:'.($result->trackId ?? 'unknown'));

        return $this->idResponse($result->trackId, $result->fromIdempotencyCache, $result->fromIdempotencyCache ? 200 : 201);
    }

    public function updateTrack(
        UpdateTrackRequest $request,
        int $track,
        UpdateTrackHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new UpdateTrackCommand(
            schoolId: $this->schoolContext->requireId(),
            trackId: $track,
            code: (string) $request->validated('code'),
            name: (string) $request->validated('name'),
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
            correlationId: CorrelationContext::id(),
        ));

        $this->audit($request->user(), 'vocational.tracks.update', 'updated', 'track:'.($result->trackId ?? 'unknown'));

        return $this->idResponse($result->trackId, $result->fromIdempotencyCache);
    }

    public function deactivateTrack(
        DeactivateTrackRequest $request,
        int $track,
        DeactivateTrackHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new DeactivateTrackCommand(
            schoolId: $this->schoolContext->requireId(),
            trackId: $track,
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
            correlationId: CorrelationContext::id(),
        ));

        $this->audit($request->user(), 'vocational.tracks.deactivate', 'deactivated', 'track:'.($result->trackId ?? 'unknown'));

        return $this->idResponse($result->trackId, $result->fromIdempotencyCache);
    }

    public function linkSubject(
        LinkSpecializationSubjectRequest $request,
        int $specialization,
        LinkSpecializationSubjectHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new LinkSpecializationSubjectCommand(
            schoolId: $this->schoolContext->requireId(),
            specializationId: $specialization,
            subjectId: (int) $request->validated('subject_id'),
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
            isRequired: (bool) ($request->validated('is_required') ?? true),
            creditHours: $request->validated('credit_hours') !== null
                ? (int) $request->validated('credit_hours')
                : null,
            correlationId: CorrelationContext::id(),
        ));

        $this->audit($request->user(), 'vocational.specialization_subjects.store', 'created', 'specialization_subject:'.($result->linkId ?? 'unknown'));

        return $this->idResponse($result->linkId, $result->fromIdempotencyCache, $result->fromIdempotencyCache ? 200 : 201);
    }

    public function deactivateSubjectLink(
        DeactivateSpecializationSubjectRequest $request,
        int $link,
        DeactivateSpecializationSubjectHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new DeactivateSpecializationSubjectCommand(
            schoolId: $this->schoolContext->requireId(),
            linkId: $link,
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
            correlationId: CorrelationContext::id(),
        ));

        $this->audit($request->user(), 'vocational.specialization_subjects.deactivate', 'deactivated', 'specialization_subject:'.($result->linkId ?? 'unknown'));

        return $this->idResponse($result->linkId, $result->fromIdempotencyCache);
    }

    public function storeWorkshop(
        CreateWorkshopRequest $request,
        CreateWorkshopHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new CreateWorkshopCommand(
            schoolId: $this->schoolContext->requireId(),
            code: (string) $request->validated('code'),
            name: (string) $request->validated('name'),
            capacity: (int) $request->validated('capacity'),
            safetyCapacity: (int) $request->validated('safety_capacity'),
            roomId: $request->validated('room_id') !== null
                ? (int) $request->validated('room_id')
                : null,
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
        ));

        if ($result->failed()) {
            return response()->json([
                'message' => 'Workshop create rejected.',
                'error_code' => $result->errors[0] ?? 'vocational.workshop_create_failed',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 422);
        }

        $this->audit(
            $request->user(),
            'vocational.workshops.store',
            'created',
            'workshop:'.$result->workshopId,
        );

        return response()->json([
            'data' => [
                'workshop_id' => $result->workshopId,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ], $result->fromIdempotencyCache ? 200 : 201);
    }

    public function indexWorkshops(
        ListWorkshopsRequest $request,
        ListWorkshopsHandler $handler,
    ): JsonResponse {
        $status = $request->validated('status');
        $items = $handler->handle(new ListWorkshopsQuery(
            schoolId: $this->schoolContext->requireId(),
            status: $status !== null ? (int) $status : null,
        ));

        $this->securityAudit->record(
            SecurityEventType::VocationalDataAccess,
            'vocational.workshops.index',
            'viewed',
            $request->user(),
            'workshops',
            ['count' => count($items)],
        );

        return response()->json([
            'data' => array_map(static fn (WorkshopDTO $dto): array => [
                'id' => $dto->id,
                'school_id' => $dto->schoolId,
                'code' => $dto->code,
                'name' => $dto->name,
                'capacity' => $dto->capacity,
                'safety_capacity' => $dto->safetyCapacity,
                'room_id' => $dto->roomId,
                'status' => $dto->status,
                'created_at' => $dto->createdAt,
                'updated_at' => $dto->updatedAt,
            ], $items),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function storeWorkshopEquipment(
        int $workshop,
        CreateWorkshopEquipmentRequest $request,
        CreateWorkshopEquipmentHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new CreateWorkshopEquipmentCommand(
            schoolId: $this->schoolContext->requireId(),
            workshopId: $workshop,
            code: (string) $request->validated('code'),
            name: (string) $request->validated('name'),
            quantity: (int) $request->validated('quantity'),
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
        ));

        if ($result->failed()) {
            $code = $result->errors[0] ?? 'vocational.equipment_create_failed';

            return response()->json([
                'message' => 'Workshop equipment create rejected.',
                'error_code' => $code,
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], $code === 'vocational.workshop_not_found' ? 404 : 422);
        }

        $this->audit($request->user(), 'vocational.workshop_equipment.store', 'created', 'equipment:'.$result->equipmentId);

        return response()->json([
            'data' => [
                'equipment_id' => $result->equipmentId,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ], $result->fromIdempotencyCache ? 200 : 201);
    }

    public function indexWorkshopEquipment(
        int $workshop,
        ListWorkshopEquipmentRequest $request,
        ListWorkshopEquipmentHandler $handler,
    ): JsonResponse {
        $status = $request->validated('equipment_status');
        $items = $handler->handle(new ListWorkshopEquipmentQuery(
            schoolId: $this->schoolContext->requireId(),
            workshopId: $workshop,
            status: $status !== null ? (int) $status : null,
        ));

        if ($items === null) {
            return response()->json([
                'message' => 'Workshop not found.',
                'error_code' => 'vocational.workshop_not_found',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 404);
        }

        $this->securityAudit->record(
            SecurityEventType::VocationalDataAccess,
            'vocational.workshop_equipment.index',
            'viewed',
            $request->user(),
            'workshop:'.$workshop,
            ['count' => count($items)],
        );

        return response()->json([
            'data' => array_map(static fn (WorkshopEquipmentDTO $dto): array => [
                'id' => $dto->id,
                'school_id' => $dto->schoolId,
                'workshop_id' => $dto->workshopId,
                'code' => $dto->code,
                'name' => $dto->name,
                'quantity' => $dto->quantity,
                'status' => $dto->status,
            ], $items),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function deactivateWorkshopEquipment(
        int $equipment,
        DeactivateWorkshopEquipmentRequest $request,
        DeactivateWorkshopEquipmentHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new DeactivateWorkshopEquipmentCommand(
            schoolId: $this->schoolContext->requireId(),
            equipmentId: $equipment,
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
        ));

        if ($result->failed()) {
            $code = $result->errors[0] ?? 'vocational.equipment_deactivate_failed';

            return response()->json([
                'message' => 'Workshop equipment deactivate rejected.',
                'error_code' => $code,
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], $code === 'vocational.equipment_not_found' ? 404 : 422);
        }

        $this->audit($request->user(), 'vocational.workshop_equipment.deactivate', 'deactivated', 'equipment:'.$result->equipmentId);

        return response()->json([
            'data' => [
                'equipment_id' => $result->equipmentId,
                'status' => 2,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function deactivateWorkshop(
        int $workshop,
        DeactivateWorkshopRequest $request,
        DeactivateWorkshopHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new DeactivateWorkshopCommand(
            schoolId: $this->schoolContext->requireId(),
            workshopId: $workshop,
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
        ));

        if ($result->failed()) {
            $code = $result->errors[0] ?? 'vocational.workshop_deactivate_failed';

            return response()->json([
                'message' => 'Workshop deactivate rejected.',
                'error_code' => $code,
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], $code === 'vocational.workshop_not_found' ? 404 : 422);
        }

        $this->audit($request->user(), 'vocational.workshops.deactivate', 'deactivated', 'workshop:'.$result->workshopId);

        return response()->json([
            'data' => [
                'workshop_id' => $result->workshopId,
                'status' => 2,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function specializationPayload(SpecializationDTO $dto): array
    {
        return [
            'id' => $dto->id,
            'school_id' => $dto->schoolId,
            'code' => $dto->code,
            'name' => $dto->name,
            'description' => $dto->description,
            'status' => $dto->status,
            'tracks' => $dto->tracks,
            'subject_links' => $dto->subjectLinks,
        ];
    }

    private function idResponse(?int $id, bool $fromIdempotencyCache, int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => ['id' => $id],
            'meta' => [
                'from_idempotency_cache' => $fromIdempotencyCache,
                'correlation_id' => CorrelationContext::id(),
            ],
        ], $status);
    }

    private function audit(mixed $user, string $action, string $outcome, string $resource): void
    {
        $this->securityAudit->record(
            SecurityEventType::VocationalDataModified,
            $action,
            $outcome,
            $user,
            $resource,
            [],
        );
    }
}
