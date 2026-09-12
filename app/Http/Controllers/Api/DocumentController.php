<?php

namespace App\Http\Controllers\Api;

use App\Application\Documents\Commands\RegisterDocumentMetadataCommand;
use App\Application\Documents\Commands\RegisterDocumentMetadataHandler;
use App\Application\Documents\DTOs\DocumentFileDTO;
use App\Application\Documents\Queries\ListDocumentsByEntityHandler;
use App\Application\Documents\Queries\ListDocumentsByEntityQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\ListDocumentsByEntityRequest;
use App\Http\Requests\Documents\RegisterDocumentMetadataRequest;
use App\Intelligence\Support\CorrelationContext;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Http\JsonResponse;

class DocumentController extends Controller
{
    public function __construct(
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly SchoolContext $schoolContext,
    ) {}

    public function store(
        RegisterDocumentMetadataRequest $request,
        RegisterDocumentMetadataHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $result = $handler->handle(new RegisterDocumentMetadataCommand(
            schoolId: $schoolId,
            entityType: (string) $request->validated('entity_type'),
            entityId: (int) $request->validated('entity_id'),
            documentType: (int) $request->validated('document_type'),
            storageKey: (string) $request->validated('storage_key'),
            fileName: (string) $request->validated('file_name'),
            mimeType: (string) $request->validated('mime_type'),
            fileSize: (int) $request->validated('file_size'),
            fileHash: (string) $request->validated('file_hash'),
            uploadedBy: $request->user()?->id,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            return response()->json([
                'message' => 'Document metadata register rejected.',
                'error_code' => $result->errors[0] ?? 'documents.register_failed',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 422);
        }

        $this->securityAudit->record(
            SecurityEventType::DocumentsDataModified,
            'documents.metadata.register',
            'registered',
            $request->user(),
            'document:'.$result->documentId,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'document_id' => $result->documentId,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ], $result->fromIdempotencyCache ? 200 : 201);
    }

    public function index(
        ListDocumentsByEntityRequest $request,
        ListDocumentsByEntityHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $items = $handler->handle(new ListDocumentsByEntityQuery(
            schoolId: $schoolId,
            entityType: (string) $request->validated('entity_type'),
            entityId: (int) $request->validated('entity_id'),
        ));

        $this->securityAudit->record(
            SecurityEventType::DocumentsDataAccess,
            'documents.metadata.list',
            'listed',
            $request->user(),
            'school:'.$schoolId,
            ['count' => count($items)],
        );

        return response()->json([
            'data' => array_map(static fn (DocumentFileDTO $dto): array => [
                'id' => $dto->id,
                'school_id' => $dto->schoolId,
                'entity_type' => $dto->entityType,
                'entity_id' => $dto->entityId,
                'document_type' => $dto->documentType,
                'storage_key' => $dto->storageKey,
                'file_name' => $dto->fileName,
                'mime_type' => $dto->mimeType,
                'file_size' => $dto->fileSize,
                'file_hash' => $dto->fileHash,
                'uploaded_by' => $dto->uploadedBy,
                'created_at' => $dto->createdAt,
            ], $items),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }
}
