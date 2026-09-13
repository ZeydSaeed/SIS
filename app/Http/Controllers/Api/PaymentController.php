<?php

namespace App\Http\Controllers\Api;

use App\Application\Finance\Commands\RecordPaymentCommand;
use App\Application\Finance\Commands\RecordPaymentHandler;
use App\Application\Finance\Commands\VoidPaymentCommand;
use App\Application\Finance\Commands\VoidPaymentHandler;
use App\Application\Finance\DTOs\PaymentDTO;
use App\Application\Finance\Queries\GetPaymentHandler;
use App\Application\Finance\Queries\GetPaymentQuery;
use App\Application\Finance\Queries\ListPaymentsHandler;
use App\Application\Finance\Queries\ListPaymentsQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ListPaymentsRequest;
use App\Http\Requests\Finance\RecordPaymentRequest;
use App\Http\Requests\Finance\ShowPaymentRequest;
use App\Http\Requests\Finance\VoidPaymentRequest;
use App\Intelligence\Support\CorrelationContext;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function __construct(
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly SchoolContext $schoolContext,
    ) {}

    public function store(
        RecordPaymentRequest $request,
        RecordPaymentHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $result = $handler->handle(new RecordPaymentCommand(
            schoolId: $schoolId,
            studentFeeId: (int) $request->validated('student_fee_id'),
            amount: (string) $request->validated('amount'),
            paymentMethod: (int) $request->validated('payment_method'),
            paymentReference: $request->validated('payment_reference'),
            paidAt: $request->validated('paid_at'),
            receivedBy: $request->user()?->id,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            return response()->json([
                'message' => 'Payment record rejected.',
                'error_code' => $result->errors[0] ?? 'finance.payment_record_failed',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 422);
        }

        $this->securityAudit->record(
            SecurityEventType::FinanceDataModified,
            'finance.payment.record',
            'recorded',
            $request->user(),
            'payment:'.$result->paymentId,
            [
                'from_idempotency' => $result->fromIdempotencyCache,
                'student_fee_status' => $result->studentFeeStatus,
            ],
        );

        return response()->json([
            'data' => [
                'payment_id' => $result->paymentId,
                'student_fee_status' => $result->studentFeeStatus,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ], $result->fromIdempotencyCache ? 200 : 201);
    }

    public function void(
        int $payment,
        VoidPaymentRequest $request,
        VoidPaymentHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $result = $handler->handle(new VoidPaymentCommand(
            schoolId: $schoolId,
            paymentId: $payment,
            voidedBy: $request->user()?->id,
            idempotencyKey: $request->header('X-Idempotency-Key'),
            notes: $request->validated('notes'),
        ));

        if ($result->failed()) {
            return response()->json([
                'message' => 'Payment void rejected.',
                'error_code' => $result->errors[0] ?? 'finance.payment_void_failed',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 422);
        }

        $this->securityAudit->record(
            SecurityEventType::FinanceDataModified,
            'finance.payment.void',
            'voided',
            $request->user(),
            'payment:'.$result->paymentId,
            [
                'from_idempotency' => $result->fromIdempotencyCache,
                'student_fee_status' => $result->studentFeeStatus,
            ],
        );

        return response()->json([
            'data' => [
                'payment_id' => $result->paymentId,
                'student_fee_status' => $result->studentFeeStatus,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function index(
        ListPaymentsRequest $request,
        ListPaymentsHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $studentFeeId = $request->validated('student_fee_id');

        $items = $handler->handle(new ListPaymentsQuery(
            schoolId: $schoolId,
            studentFeeId: $studentFeeId !== null ? (int) $studentFeeId : null,
        ));

        $this->securityAudit->record(
            SecurityEventType::FinanceDataAccess,
            'finance.payment.list',
            'listed',
            $request->user(),
            'school:'.$schoolId,
            ['count' => count($items)],
        );

        return response()->json([
            'data' => array_map(static fn (PaymentDTO $dto): array => [
                'id' => $dto->id,
                'school_id' => $dto->schoolId,
                'student_fee_id' => $dto->studentFeeId,
                'amount' => $dto->amount,
                'payment_method' => $dto->paymentMethod,
                'payment_reference' => $dto->paymentReference,
                'paid_at' => $dto->paidAt,
                'received_by' => $dto->receivedBy,
                'status' => $dto->status,
                'voided_at' => $dto->voidedAt,
                'voided_by' => $dto->voidedBy,
                'created_at' => $dto->createdAt,
            ], $items),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function show(
        int $payment,
        ShowPaymentRequest $request,
        GetPaymentHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $dto = $handler->handle(new GetPaymentQuery(
            schoolId: $schoolId,
            paymentId: $payment,
        ));

        if ($dto === null) {
            return response()->json([
                'message' => 'Payment not found.',
                'error_code' => 'finance.payment_not_found',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 404);
        }

        $this->securityAudit->record(
            SecurityEventType::FinanceDataAccess,
            'finance.payment.show',
            'viewed',
            $request->user(),
            'payment:'.$payment,
            [],
        );

        return response()->json([
            'data' => [
                'id' => $dto->id,
                'school_id' => $dto->schoolId,
                'student_fee_id' => $dto->studentFeeId,
                'amount' => $dto->amount,
                'payment_method' => $dto->paymentMethod,
                'payment_reference' => $dto->paymentReference,
                'paid_at' => $dto->paidAt,
                'received_by' => $dto->receivedBy,
                'status' => $dto->status,
                'voided_at' => $dto->voidedAt,
                'voided_by' => $dto->voidedBy,
                'created_at' => $dto->createdAt,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }
}
