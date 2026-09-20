<?php

namespace App\Http\Controllers\Api;

use App\Application\Student\Commands\CreateStudentHandler;
use App\Application\Student\Commands\RegisterStudentDocumentCommand;
use App\Application\Student\Commands\RegisterStudentDocumentHandler;
use App\Application\Student\Commands\RestoreStudentDocumentCommand;
use App\Application\Student\Commands\RestoreStudentDocumentHandler;
use App\Application\Student\Commands\UpdateStudentHandler;
use App\Application\Student\Commands\UploadStudentDocumentCommand;
use App\Application\Student\Commands\UploadStudentDocumentHandler;
use App\Application\Student\Commands\VoidStudentDocumentCommand;
use App\Application\Student\Commands\VoidStudentDocumentHandler;
use App\Application\Student\DTOs\StudentDocumentDTO;
use App\Application\Student\DTOs\StudentGuardianLinkDTO;
use App\Application\Student\Queries\GetStudentDocumentContentHandler;
use App\Application\Student\Queries\GetStudentDocumentContentQuery;
use App\Application\Student\Queries\GetStudentDocumentHandler;
use App\Application\Student\Queries\GetStudentDocumentQuery;
use App\Application\Student\Queries\GetStudentHandler;
use App\Application\Student\Queries\GetStudentQuery;
use App\Application\Student\Queries\ListStudentDocumentsHandler;
use App\Application\Student\Queries\ListStudentDocumentsQuery;
use App\Application\Student\Queries\ListStudentGuardiansHandler;
use App\Application\Student\Queries\ListStudentGuardiansQuery;
use App\Application\Student\Queries\ListStudentsHandler;
use App\Application\Student\Queries\ListStudentsQuery;
use App\Application\Student\Queries\SearchStudentsHandler;
use App\Application\Student\Queries\SearchStudentsQuery;
use App\Domain\Student\Exceptions\StudentNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\CreateStudentRequest;
use App\Http\Requests\Student\DownloadStudentDocumentRequest;
use App\Http\Requests\Student\ListStudentDocumentsRequest;
use App\Http\Requests\Student\ListStudentGuardiansRequest;
use App\Http\Requests\Student\RegisterStudentDocumentRequest;
use App\Http\Requests\Student\RestoreStudentDocumentRequest;
use App\Http\Requests\Student\ShowStudentDocumentRequest;
use App\Http\Requests\Student\UpdateStudentRequest;
use App\Http\Requests\Student\UploadStudentDocumentRequest;
use App\Http\Requests\Student\VoidStudentDocumentRequest;
use App\Infrastructure\Persistence\Eloquent\StudentRecord;
use App\Intelligence\Support\CorrelationContext;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use App\Security\Support\StudentResponseSanitizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentController extends Controller
{
    public function __construct(
        private readonly StudentResponseSanitizer $sanitizer,
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly SchoolContext $schoolContext,
    ) {}

    public function index(Request $request, ListStudentsHandler $handler): JsonResponse
    {
        $this->authorize('viewAny', StudentRecord::class);

        $status = $request->filled('status') ? (int) $request->query('status') : null;
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(max(1, (int) $request->query('per_page', 25)), 100);
        $schoolId = $this->schoolContext->requireId();
        $academicYearId = $request->filled('academic_year_id') ? (int) $request->query('academic_year_id') : null;
        $gender = $this->queryGender($request);

        $result = $handler->handle(new ListStudentsQuery(
            status: $status,
            schoolId: $schoolId,
            page: $page,
            perPage: $perPage,
            academicYearId: $academicYearId,
            gender: $gender,
        ));

        $payload = $result->toArray();
        $payload['data'] = $this->sanitizer->sanitizeList($result->items, $request->user());

        $this->securityAudit->record(
            SecurityEventType::StudentDataAccess,
            'students.index',
            'allowed',
            $request->user(),
            'students',
            ['page' => $page, 'per_page' => $perPage],
        );

        return response()->json($payload);
    }

    public function search(Request $request, SearchStudentsHandler $handler): JsonResponse
    {
        $this->authorize('viewAny', StudentRecord::class);

        $term = (string) $request->query('q', '');
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(max(1, (int) $request->query('per_page', 25)), 100);
        $schoolId = $this->schoolContext->requireId();
        $academicYearId = $request->filled('academic_year_id') ? (int) $request->query('academic_year_id') : null;
        $status = $request->filled('status') ? (int) $request->query('status') : null;
        $gender = $this->queryGender($request);

        $result = $handler->handle(new SearchStudentsQuery(
            term: $term,
            schoolId: $schoolId,
            page: $page,
            perPage: $perPage,
            status: $status,
            academicYearId: $academicYearId,
            gender: $gender,
        ));

        $payload = $result->toArray();
        $payload['data'] = $this->sanitizer->sanitizeList($result->items, $request->user());

        $this->securityAudit->record(
            SecurityEventType::StudentDataAccess,
            'students.search',
            'allowed',
            $request->user(),
            'students',
            ['term_length' => strlen($term)],
        );

        return response()->json($payload);
    }

    public function show(Request $request, int $student, GetStudentHandler $handler): JsonResponse
    {
        $record = StudentRecord::query()->find($student);

        if ($record === null) {
            throw StudentNotFoundException::forId($student);
        }

        try {
            $this->authorize('view', $record);
        } catch (AuthorizationException) {
            $this->securityAudit->record(
                SecurityEventType::IdorBlocked,
                'students.show',
                'denied',
                $request->user(),
                "student:{$student}",
            );

            throw new AuthorizationException('This action is unauthorized.');
        }

        $schoolId = $this->schoolContext->requireId();
        $academicYearId = $request->filled('academic_year_id') ? (int) $request->query('academic_year_id') : null;
        $detail = $handler->handle(new GetStudentQuery($student, $schoolId, $academicYearId));

        $this->securityAudit->record(
            SecurityEventType::StudentDataAccess,
            'students.show',
            'allowed',
            $request->user(),
            "student:{$student}",
        );

        return response()->json([
            'data' => $this->sanitizer->sanitizeDetail($detail, $request->user()),
        ]);
    }

    public function store(
        CreateStudentRequest $request,
        CreateStudentHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();

        $result = $handler->handle(StudentProfileCommandFactory::createFromRequest($request, $schoolId));

        $this->securityAudit->record(
            SecurityEventType::StudentDataModified,
            'students.store',
            'created',
            $request->user(),
            "student:{$result->studentId}",
        );

        return response()->json([
            'data' => [
                'id' => $result->studentId,
                'student_code' => $result->studentCode,
            ],
            'meta' => [
                'from_idempotency_cache' => $result->fromIdempotencyCache,
                'correlation_id' => CorrelationContext::id(),
            ],
        ], 201);
    }

    public function update(
        UpdateStudentRequest $request,
        int $student,
        UpdateStudentHandler $handler,
    ): JsonResponse {
        $record = StudentRecord::query()->find($student);

        try {
            $this->authorize('update', $record);
        } catch (AuthorizationException) {
            $this->securityAudit->record(
                SecurityEventType::IdorBlocked,
                'students.update',
                'denied',
                $request->user(),
                "student:{$student}",
            );

            throw new AuthorizationException('This action is unauthorized.');
        }

        $result = $handler->handle(StudentProfileCommandFactory::updateFromRequest($request, $student));

        $this->securityAudit->record(
            SecurityEventType::StudentDataModified,
            'students.update',
            'updated',
            $request->user(),
            "student:{$student}",
        );

        return response()->json([
            'data' => [
                'id' => $result->studentId,
            ],
            'meta' => [
                'correlation_id' => CorrelationContext::id(),
            ],
        ]);
    }

    public function storeDocument(
        int $student,
        RegisterStudentDocumentRequest $request,
        RegisterStudentDocumentHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new RegisterStudentDocumentCommand(
            schoolId: $this->schoolContext->requireId(),
            studentId: $student,
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
            $code = $result->errors[0] ?? 'student.document_register_failed';

            return response()->json([
                'message' => 'Student document register rejected.',
                'error_code' => $code,
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], $code === 'student.not_found' ? 404 : 422);
        }

        $this->securityAudit->record(
            SecurityEventType::StudentDataModified,
            'students.documents.store',
            'registered',
            $request->user(),
            'student:'.$student,
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

    public function uploadDocument(
        int $student,
        UploadStudentDocumentRequest $request,
        UploadStudentDocumentHandler $handler,
    ): JsonResponse {
        $file = $request->file('file');
        /** @var list<string> $allowedMimes */
        $allowedMimes = config('sis.documents.allowed_mimes', [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/webp',
            'text/plain',
        ]);

        $result = $handler->handle(new UploadStudentDocumentCommand(
            schoolId: $this->schoolContext->requireId(),
            studentId: $student,
            documentType: (int) $request->validated('document_type'),
            fileName: (string) $file->getClientOriginalName(),
            mimeType: (string) ($file->getMimeType() ?: 'application/octet-stream'),
            contents: (string) file_get_contents($file->getRealPath()),
            maxBytes: (int) config('sis.documents.max_bytes', 10 * 1024 * 1024),
            allowedMimes: $allowedMimes,
            uploadedBy: $request->user()?->id,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            $code = $result->errors[0] ?? 'student.document_upload_failed';

            return response()->json([
                'message' => 'Student document upload rejected.',
                'error_code' => $code,
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], $code === 'student.not_found' ? 404 : 422);
        }

        $this->securityAudit->record(
            SecurityEventType::StudentDataModified,
            'students.documents.binary.upload',
            'uploaded',
            $request->user(),
            'student:'.$student,
            [
                'from_idempotency' => $result->fromIdempotencyCache,
                'storage_key' => $result->storageKey,
            ],
        );

        return response()->json([
            'data' => [
                'document_id' => $result->documentId,
                'storage_key' => $result->storageKey,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ], $result->fromIdempotencyCache ? 200 : 201);
    }

    public function showDocument(
        int $document,
        ShowStudentDocumentRequest $request,
        GetStudentDocumentHandler $handler,
    ): JsonResponse {
        $dto = $handler->handle(new GetStudentDocumentQuery(
            schoolId: $this->schoolContext->requireId(),
            documentId: $document,
        ));

        if ($dto === null) {
            return response()->json([
                'message' => 'Student document not found.',
                'error_code' => 'student.document_not_found',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 404);
        }

        $this->securityAudit->record(
            SecurityEventType::StudentDataAccess,
            'students.documents.show',
            'viewed',
            $request->user(),
            'student_document:'.$document,
            [],
        );

        return response()->json([
            'data' => [
                'id' => $dto->id,
                'student_id' => $dto->studentId,
                'document_type' => $dto->documentType,
                'storage_key' => $dto->storageKey,
                'file_name' => $dto->fileName,
                'mime_type' => $dto->mimeType,
                'file_size' => $dto->fileSize,
                'file_hash' => $dto->fileHash,
                'status' => $dto->status,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function downloadDocument(
        int $document,
        DownloadStudentDocumentRequest $request,
        GetStudentDocumentContentHandler $handler,
    ): Response|StreamedResponse|JsonResponse {
        $dto = $handler->handle(new GetStudentDocumentContentQuery(
            schoolId: $this->schoolContext->requireId(),
            documentId: $document,
        ));

        if ($dto === null) {
            return response()->json([
                'message' => 'Student document not found.',
                'error_code' => 'student.document_not_found',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 404);
        }

        $this->securityAudit->record(
            SecurityEventType::StudentDataAccess,
            'students.documents.binary.download',
            'downloaded',
            $request->user(),
            'student_document:'.$dto->documentId,
            [],
        );

        return response($dto->contents, 200, [
            'Content-Type' => $dto->mimeType,
            'Content-Disposition' => 'attachment; filename="'.$dto->fileName.'"',
            'X-Content-SHA256' => $dto->fileHash,
            'X-Correlation-Id' => (string) CorrelationContext::id(),
        ]);
    }

    public function indexDocuments(
        int $student,
        ListStudentDocumentsRequest $request,
        ListStudentDocumentsHandler $handler,
    ): JsonResponse {
        $items = $handler->handle(new ListStudentDocumentsQuery(
            $this->schoolContext->requireId(),
            $student,
        ));

        if ($items === null) {
            return response()->json([
                'message' => 'Student not found.',
                'error_code' => 'student.not_found',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 404);
        }

        $this->securityAudit->record(
            SecurityEventType::StudentDataAccess,
            'students.documents.index',
            'listed',
            $request->user(),
            'student:'.$student,
            ['count' => count($items)],
        );

        return response()->json([
            'data' => array_map(fn (StudentDocumentDTO $dto): array => [
                'id' => $dto->id,
                'student_id' => $dto->studentId,
                'document_type' => $dto->documentType,
                'storage_key' => $dto->storageKey,
                'file_name' => $dto->fileName,
                'mime_type' => $dto->mimeType,
                'file_size' => $dto->fileSize,
                'file_hash' => $dto->fileHash,
                'status' => $dto->status,
            ], $items),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function indexGuardians(
        int $student,
        ListStudentGuardiansRequest $request,
        ListStudentGuardiansHandler $handler,
    ): JsonResponse {
        $items = $handler->handle(new ListStudentGuardiansQuery(
            schoolId: $this->schoolContext->requireId(),
            studentId: $student,
        ));

        if ($items === null) {
            return response()->json([
                'message' => 'Student not found.',
                'error_code' => 'student.not_found',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 404);
        }

        $this->securityAudit->record(
            SecurityEventType::StudentDataAccess,
            'students.guardians.index',
            'listed',
            $request->user(),
            'student:'.$student,
            ['count' => count($items)],
        );

        return response()->json([
            'data' => array_map(static fn (StudentGuardianLinkDTO $dto): array => [
                'link_id' => $dto->linkId,
                'student_id' => $dto->studentId,
                'guardian_id' => $dto->guardianId,
                'relationship_type' => $dto->relationshipType,
                'is_primary' => $dto->isPrimary,
                'is_emergency_contact' => $dto->isEmergencyContact,
                'guardian_full_name' => $dto->guardianFullName,
                'guardian_phone' => $dto->guardianPhone,
                'guardian_email' => $dto->guardianEmail,
                'created_at' => $dto->createdAt,
            ], $items),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function voidDocument(
        int $document,
        VoidStudentDocumentRequest $request,
        VoidStudentDocumentHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new VoidStudentDocumentCommand(
            schoolId: $this->schoolContext->requireId(),
            documentId: $document,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            $code = $result->errors[0] ?? 'student.document_void_failed';

            return response()->json([
                'message' => 'Student document void rejected.',
                'error_code' => $code,
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], $code === 'student.document_not_found' ? 404 : 422);
        }

        $this->securityAudit->record(
            SecurityEventType::StudentDataModified,
            'students.documents.void',
            'voided',
            $request->user(),
            'student_document:'.$result->documentId,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'document_id' => $result->documentId,
                'status' => 2,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function restoreDocument(
        int $document,
        RestoreStudentDocumentRequest $request,
        RestoreStudentDocumentHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new RestoreStudentDocumentCommand(
            schoolId: $this->schoolContext->requireId(),
            documentId: $document,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            $code = $result->errors[0] ?? 'student.document_restore_failed';

            return response()->json([
                'message' => 'Student document restore rejected.',
                'error_code' => $code,
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], $code === 'student.document_not_found' ? 404 : 422);
        }

        $this->securityAudit->record(
            SecurityEventType::StudentDataModified,
            'students.documents.restore',
            'restored',
            $request->user(),
            'student_document:'.$result->documentId,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'document_id' => $result->documentId,
                'status' => 1,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    private function queryGender(Request $request): ?int
    {
        if (! $request->filled('gender')) {
            return null;
        }

        $gender = (int) $request->query('gender');

        return $gender === 1 || $gender === 2 ? $gender : null;
    }
}
