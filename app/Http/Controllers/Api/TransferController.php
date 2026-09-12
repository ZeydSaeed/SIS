<?php

namespace App\Http\Controllers\Api;

use App\Application\Transfers\Commands\ApproveTransferRequestCommand;
use App\Application\Transfers\Commands\ApproveTransferRequestHandler;
use App\Application\Transfers\Commands\CancelTransferRequestCommand;
use App\Application\Transfers\Commands\CancelTransferRequestHandler;
use App\Application\Transfers\Commands\CompleteTransferCommand;
use App\Application\Transfers\Commands\CompleteTransferHandler;
use App\Application\Transfers\Commands\CreateTransferRequestCommand;
use App\Application\Transfers\Commands\CreateTransferRequestHandler;
use App\Application\Transfers\Commands\RejectTransferRequestCommand;
use App\Application\Transfers\Commands\RejectTransferRequestHandler;
use App\Application\Transfers\DTOs\TransferRequestDTO;
use App\Application\Transfers\Queries\ListTransferRequestsHandler;
use App\Application\Transfers\Queries\ListTransferRequestsQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transfers\CompleteTransferRequestRequest;
use App\Http\Requests\Transfers\CreateTransferRequestRequest;
use App\Http\Requests\Transfers\DecideTransferRequestRequest;
use App\Http\Requests\Transfers\ListTransferRequestsRequest;
use App\Intelligence\Support\CorrelationContext;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Http\JsonResponse;

class TransferController extends Controller
{
    public function __construct(
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly SchoolContext $schoolContext,
    ) {}

    public function store(
        CreateTransferRequestRequest $request,
        CreateTransferRequestHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $result = $handler->handle(new CreateTransferRequestCommand(
            fromSchoolId: $schoolId,
            toSchoolId: (int) $request->validated('to_school_id'),
            fromEnrollmentId: (int) $request->validated('from_enrollment_id'),
            academicYearId: (int) $request->validated('academic_year_id'),
            reason: $request->validated('reason'),
            requestedBy: $request->user()?->id,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            return response()->json([
                'message' => 'Transfer request create rejected.',
                'error_code' => $result->errors[0] ?? 'transfers.create_failed',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 422);
        }

        $this->securityAudit->record(
            SecurityEventType::TransfersDataModified,
            'transfers.request.create',
            'created',
            $request->user(),
            'transfer_request:'.$result->transferRequestId,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'transfer_request_id' => $result->transferRequestId,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ], $result->fromIdempotencyCache ? 200 : 201);
    }

    public function index(
        ListTransferRequestsRequest $request,
        ListTransferRequestsHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $items = $handler->handle(new ListTransferRequestsQuery(
            schoolId: $schoolId,
            academicYearId: $request->validated('academic_year_id') !== null
                ? (int) $request->validated('academic_year_id')
                : null,
            status: $request->validated('request_status') !== null
                ? (int) $request->validated('request_status')
                : null,
        ));

        $this->securityAudit->record(
            SecurityEventType::TransfersDataAccess,
            'transfers.request.list',
            'listed',
            $request->user(),
            'school:'.$schoolId,
            ['count' => count($items)],
        );

        return response()->json([
            'data' => array_map(static fn (TransferRequestDTO $dto): array => [
                'id' => $dto->id,
                'student_id' => $dto->studentId,
                'from_school_id' => $dto->fromSchoolId,
                'to_school_id' => $dto->toSchoolId,
                'from_enrollment_id' => $dto->fromEnrollmentId,
                'academic_year_id' => $dto->academicYearId,
                'reason' => $dto->reason,
                'status' => $dto->status,
                'requested_by' => $dto->requestedBy,
                'requested_at' => $dto->requestedAt,
                'approved_by' => $dto->approvedBy,
                'approved_at' => $dto->approvedAt,
                'created_at' => $dto->createdAt,
            ], $items),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function approve(
        int $transferRequest,
        DecideTransferRequestRequest $request,
        ApproveTransferRequestHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $result = $handler->handle(new ApproveTransferRequestCommand(
            toSchoolId: $schoolId,
            transferRequestId: $transferRequest,
            approvedBy: $request->user()?->id,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            return response()->json([
                'message' => 'Transfer approve rejected.',
                'error_code' => $result->errors[0] ?? 'transfers.approve_failed',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 422);
        }

        $this->securityAudit->record(
            SecurityEventType::TransfersDataModified,
            'transfers.request.approve',
            'approved',
            $request->user(),
            'transfer_request:'.$transferRequest,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'transfer_request_id' => $result->transferRequestId,
                'status' => 2,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function reject(
        int $transferRequest,
        DecideTransferRequestRequest $request,
        RejectTransferRequestHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $result = $handler->handle(new RejectTransferRequestCommand(
            toSchoolId: $schoolId,
            transferRequestId: $transferRequest,
            approvedBy: $request->user()?->id,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            return response()->json([
                'message' => 'Transfer reject rejected.',
                'error_code' => $result->errors[0] ?? 'transfers.reject_failed',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 422);
        }

        $this->securityAudit->record(
            SecurityEventType::TransfersDataModified,
            'transfers.request.reject',
            'rejected',
            $request->user(),
            'transfer_request:'.$transferRequest,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'transfer_request_id' => $result->transferRequestId,
                'status' => 3,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function complete(
        int $transferRequest,
        CompleteTransferRequestRequest $request,
        CompleteTransferHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $result = $handler->handle(new CompleteTransferCommand(
            toSchoolId: $schoolId,
            transferRequestId: $transferRequest,
            toClassId: (int) $request->validated('to_class_id'),
            toSectionId: (int) $request->validated('to_section_id'),
            effectiveDate: (string) $request->validated('effective_date'),
            specializationId: $request->validated('specialization_id') !== null
                ? (int) $request->validated('specialization_id')
                : null,
            completedBy: $request->user()?->id,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            return response()->json([
                'message' => 'Transfer complete rejected.',
                'error_code' => $result->errors[0] ?? 'transfers.complete_failed',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 422);
        }

        $this->securityAudit->record(
            SecurityEventType::TransfersDataModified,
            'transfers.request.complete',
            'completed',
            $request->user(),
            'transfer_request:'.$transferRequest,
            [
                'transfer_record_id' => $result->transferRecordId,
                'to_enrollment_id' => $result->toEnrollmentId,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
        );

        return response()->json([
            'data' => [
                'transfer_request_id' => $result->transferRequestId,
                'transfer_record_id' => $result->transferRecordId,
                'to_enrollment_id' => $result->toEnrollmentId,
                'status' => 4,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ], $result->fromIdempotencyCache ? 200 : 201);
    }

    public function cancel(
        int $transferRequest,
        DecideTransferRequestRequest $request,
        CancelTransferRequestHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $result = $handler->handle(new CancelTransferRequestCommand(
            schoolId: $schoolId,
            transferRequestId: $transferRequest,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            return response()->json([
                'message' => 'Transfer cancel rejected.',
                'error_code' => $result->errors[0] ?? 'transfers.cancel_failed',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 422);
        }

        $this->securityAudit->record(
            SecurityEventType::TransfersDataModified,
            'transfers.request.cancel',
            'cancelled',
            $request->user(),
            'transfer_request:'.$transferRequest,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'transfer_request_id' => $result->transferRequestId,
                'status' => 5,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }
}
