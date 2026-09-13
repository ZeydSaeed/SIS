<?php

namespace App\Http\Controllers\Api;

use App\Application\Communication\Commands\MarkMessageSentCommand;
use App\Application\Communication\Commands\MarkMessageSentHandler;
use App\Application\Communication\Commands\QueueMessageCommand;
use App\Application\Communication\Commands\QueueMessageHandler;
use App\Application\Communication\DTOs\MessageDTO;
use App\Application\Communication\Queries\ListMessagesHandler;
use App\Application\Communication\Queries\ListMessagesQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Communication\ListMessagesRequest;
use App\Http\Requests\Communication\MarkMessageSentRequest;
use App\Http\Requests\Communication\QueueMessageRequest;
use App\Intelligence\Support\CorrelationContext;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Http\JsonResponse;

class MessageController extends Controller
{
    public function __construct(
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly SchoolContext $schoolContext,
    ) {}

    public function store(
        QueueMessageRequest $request,
        QueueMessageHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $result = $handler->handle(new QueueMessageCommand(
            schoolId: $schoolId,
            recipientType: (string) $request->validated('recipient_type'),
            recipientId: (int) $request->validated('recipient_id'),
            channel: (int) $request->validated('channel'),
            body: (string) $request->validated('body'),
            subject: $request->validated('subject'),
            templateId: $request->validated('template_id') !== null
                ? (int) $request->validated('template_id')
                : null,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            return response()->json([
                'message' => 'Message queue rejected.',
                'error_code' => $result->errors[0] ?? 'communication.message_queue_failed',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 422);
        }

        $this->securityAudit->record(
            SecurityEventType::CommunicationDataModified,
            'communication.message.queue',
            'queued',
            $request->user(),
            'message:'.$result->messageId,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'message_id' => $result->messageId,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ], $result->fromIdempotencyCache ? 200 : 201);
    }

    public function markSent(
        int $message,
        MarkMessageSentRequest $request,
        MarkMessageSentHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $result = $handler->handle(new MarkMessageSentCommand(
            schoolId: $schoolId,
            messageId: $message,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            return response()->json([
                'message' => 'Message mark-sent rejected.',
                'error_code' => $result->errors[0] ?? 'communication.message_mark_sent_failed',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 422);
        }

        $this->securityAudit->record(
            SecurityEventType::CommunicationDataModified,
            'communication.message.mark_sent',
            'sent',
            $request->user(),
            'message:'.$result->messageId,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'message_id' => $result->messageId,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function index(
        ListMessagesRequest $request,
        ListMessagesHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $recipientType = $request->validated('recipient_type');
        $recipientId = $request->validated('recipient_id');
        $messageStatus = $request->validated('message_status');

        $items = $handler->handle(new ListMessagesQuery(
            schoolId: $schoolId,
            recipientType: $recipientType !== null ? (string) $recipientType : null,
            recipientId: $recipientId !== null ? (int) $recipientId : null,
            messageStatus: $messageStatus !== null ? (int) $messageStatus : null,
        ));

        $this->securityAudit->record(
            SecurityEventType::CommunicationDataAccess,
            'communication.message.list',
            'listed',
            $request->user(),
            'school:'.$schoolId,
            ['count' => count($items)],
        );

        return response()->json([
            'data' => array_map(static fn (MessageDTO $dto): array => [
                'id' => $dto->id,
                'school_id' => $dto->schoolId,
                'template_id' => $dto->templateId,
                'recipient_type' => $dto->recipientType,
                'recipient_id' => $dto->recipientId,
                'channel' => $dto->channel,
                'subject' => $dto->subject,
                'body' => $dto->body,
                'status' => $dto->status,
                'sent_at' => $dto->sentAt,
                'created_at' => $dto->createdAt,
            ], $items),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }
}
