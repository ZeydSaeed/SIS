<?php

namespace App\Http\Controllers\Api;

use App\Application\Communication\Commands\CreateNotificationTemplateCommand;
use App\Application\Communication\Commands\CreateNotificationTemplateHandler;
use App\Application\Communication\Commands\DeactivateNotificationTemplateCommand;
use App\Application\Communication\Commands\DeactivateNotificationTemplateHandler;
use App\Application\Communication\Commands\ReactivateNotificationTemplateCommand;
use App\Application\Communication\Commands\ReactivateNotificationTemplateHandler;
use App\Application\Communication\DTOs\NotificationTemplateDTO;
use App\Application\Communication\Queries\GetNotificationTemplateHandler;
use App\Application\Communication\Queries\GetNotificationTemplateQuery;
use App\Application\Communication\Queries\ListNotificationTemplatesHandler;
use App\Application\Communication\Queries\ListNotificationTemplatesQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Communication\CreateNotificationTemplateRequest;
use App\Http\Requests\Communication\DeactivateNotificationTemplateRequest;
use App\Http\Requests\Communication\ListNotificationTemplatesRequest;
use App\Http\Requests\Communication\ReactivateNotificationTemplateRequest;
use App\Http\Requests\Communication\ShowNotificationTemplateRequest;
use App\Intelligence\Support\CorrelationContext;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Http\JsonResponse;

class NotificationTemplateController extends Controller
{
    public function __construct(
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly SchoolContext $schoolContext,
    ) {}

    public function store(
        CreateNotificationTemplateRequest $request,
        CreateNotificationTemplateHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $result = $handler->handle(new CreateNotificationTemplateCommand(
            schoolId: $schoolId,
            code: (string) $request->validated('code'),
            name: (string) $request->validated('name'),
            channel: (int) $request->validated('channel'),
            subjectTemplate: $request->validated('subject_template'),
            bodyTemplate: (string) $request->validated('body_template'),
            isActive: $request->boolean('is_active', true),
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            return response()->json([
                'message' => 'Notification template create rejected.',
                'error_code' => $result->errors[0] ?? 'communication.template_create_failed',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 422);
        }

        $this->securityAudit->record(
            SecurityEventType::CommunicationDataModified,
            'communication.template.create',
            'created',
            $request->user(),
            'template:'.$result->templateId,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'template_id' => $result->templateId,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ], $result->fromIdempotencyCache ? 200 : 201);
    }

    public function index(
        ListNotificationTemplatesRequest $request,
        ListNotificationTemplatesHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $activeOnly = $request->has('active_only')
            ? $request->boolean('active_only')
            : null;

        $items = $handler->handle(new ListNotificationTemplatesQuery(
            schoolId: $schoolId,
            activeOnly: $activeOnly === true ? true : null,
        ));

        $this->securityAudit->record(
            SecurityEventType::CommunicationDataAccess,
            'communication.template.list',
            'listed',
            $request->user(),
            'school:'.$schoolId,
            ['count' => count($items)],
        );

        return response()->json([
            'data' => array_map(static fn (NotificationTemplateDTO $dto): array => [
                'id' => $dto->id,
                'school_id' => $dto->schoolId,
                'code' => $dto->code,
                'name' => $dto->name,
                'channel' => $dto->channel,
                'subject_template' => $dto->subjectTemplate,
                'body_template' => $dto->bodyTemplate,
                'is_active' => $dto->isActive,
                'created_at' => $dto->createdAt,
            ], $items),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function show(
        int $template,
        ShowNotificationTemplateRequest $request,
        GetNotificationTemplateHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $dto = $handler->handle(new GetNotificationTemplateQuery(
            schoolId: $schoolId,
            templateId: $template,
        ));

        if ($dto === null) {
            return response()->json([
                'message' => 'Notification template not found.',
                'error_code' => 'communication.template_not_found',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 404);
        }

        $this->securityAudit->record(
            SecurityEventType::CommunicationDataAccess,
            'communication.template.show',
            'viewed',
            $request->user(),
            'template:'.$template,
            [],
        );

        return response()->json([
            'data' => [
                'id' => $dto->id,
                'school_id' => $dto->schoolId,
                'code' => $dto->code,
                'name' => $dto->name,
                'channel' => $dto->channel,
                'subject_template' => $dto->subjectTemplate,
                'body_template' => $dto->bodyTemplate,
                'is_active' => $dto->isActive,
                'created_at' => $dto->createdAt,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function deactivate(
        int $template,
        DeactivateNotificationTemplateRequest $request,
        DeactivateNotificationTemplateHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new DeactivateNotificationTemplateCommand(
            schoolId: $this->schoolContext->requireId(),
            templateId: $template,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            $code = $result->errors[0] ?? 'communication.template_deactivate_failed';

            return response()->json([
                'message' => 'Notification template deactivate rejected.',
                'error_code' => $code,
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], $code === 'communication.template_not_found' ? 404 : 422);
        }

        $this->securityAudit->record(
            SecurityEventType::CommunicationDataModified,
            'communication.template.deactivate',
            'deactivated',
            $request->user(),
            'template:'.$template,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'template_id' => $result->templateId,
                'is_active' => false,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function reactivate(
        int $template,
        ReactivateNotificationTemplateRequest $request,
        ReactivateNotificationTemplateHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new ReactivateNotificationTemplateCommand(
            schoolId: $this->schoolContext->requireId(),
            templateId: $template,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            $code = $result->errors[0] ?? 'communication.template_reactivate_failed';

            return response()->json([
                'message' => 'Notification template reactivate rejected.',
                'error_code' => $code,
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], $code === 'communication.template_not_found' ? 404 : 422);
        }

        $this->securityAudit->record(
            SecurityEventType::CommunicationDataModified,
            'communication.template.reactivate',
            'reactivated',
            $request->user(),
            'template:'.$template,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'template_id' => $result->templateId,
                'is_active' => true,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }
}
