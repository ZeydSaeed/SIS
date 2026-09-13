<?php

namespace App\Http\Controllers\Api;

use App\Application\Finance\Commands\AssignStudentFeeCommand;
use App\Application\Finance\Commands\AssignStudentFeeHandler;
use App\Application\Finance\Commands\CancelStudentFeeCommand;
use App\Application\Finance\Commands\CancelStudentFeeHandler;
use App\Application\Finance\Commands\ReopenStudentFeeCommand;
use App\Application\Finance\Commands\ReopenStudentFeeHandler;
use App\Application\Finance\DTOs\StudentFeeDTO;
use App\Application\Finance\Queries\GetStudentFeeHandler;
use App\Application\Finance\Queries\GetStudentFeeQuery;
use App\Application\Finance\Queries\ListStudentFeesHandler;
use App\Application\Finance\Queries\ListStudentFeesQuery;
use App\Domain\Finance\ValueObjects\StudentFeeStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\AssignStudentFeeRequest;
use App\Http\Requests\Finance\CancelStudentFeeRequest;
use App\Http\Requests\Finance\ListStudentFeesRequest;
use App\Http\Requests\Finance\ReopenStudentFeeRequest;
use App\Http\Requests\Finance\ShowStudentFeeRequest;
use App\Intelligence\Support\CorrelationContext;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Http\JsonResponse;

class StudentFeeController extends Controller
{
    public function __construct(
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly SchoolContext $schoolContext,
    ) {}

    public function store(
        AssignStudentFeeRequest $request,
        AssignStudentFeeHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $result = $handler->handle(new AssignStudentFeeCommand(
            schoolId: $schoolId,
            enrollmentId: (int) $request->validated('enrollment_id'),
            feeTypeId: (int) $request->validated('fee_type_id'),
            academicYearId: (int) $request->validated('academic_year_id'),
            amountOverride: $request->validated('amount'),
            dueDate: $request->validated('due_date'),
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            return response()->json([
                'message' => 'Student fee assign rejected.',
                'error_code' => $result->errors[0] ?? 'finance.student_fee_assign_failed',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 422);
        }

        $this->securityAudit->record(
            SecurityEventType::FinanceDataModified,
            'finance.student_fee.assign',
            'assigned',
            $request->user(),
            'student_fee:'.$result->studentFeeId,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'student_fee_id' => $result->studentFeeId,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ], $result->fromIdempotencyCache ? 200 : 201);
    }

    public function index(
        ListStudentFeesRequest $request,
        ListStudentFeesHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $enrollmentId = $request->validated('enrollment_id');
        $academicYearId = $request->validated('academic_year_id');
        $feeStatus = $request->validated('fee_status');

        $items = $handler->handle(new ListStudentFeesQuery(
            schoolId: $schoolId,
            enrollmentId: $enrollmentId !== null ? (int) $enrollmentId : null,
            academicYearId: $academicYearId !== null ? (int) $academicYearId : null,
            feeStatus: $feeStatus !== null ? (int) $feeStatus : null,
        ));

        $this->securityAudit->record(
            SecurityEventType::FinanceDataAccess,
            'finance.student_fee.list',
            'listed',
            $request->user(),
            'school:'.$schoolId,
            ['count' => count($items)],
        );

        return response()->json([
            'data' => array_map(static fn (StudentFeeDTO $dto): array => [
                'id' => $dto->id,
                'school_id' => $dto->schoolId,
                'enrollment_id' => $dto->enrollmentId,
                'fee_type_id' => $dto->feeTypeId,
                'academic_year_id' => $dto->academicYearId,
                'amount' => $dto->amount,
                'due_date' => $dto->dueDate,
                'status' => $dto->status,
                'created_at' => $dto->createdAt,
            ], $items),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function show(
        int $studentFee,
        ShowStudentFeeRequest $request,
        GetStudentFeeHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $dto = $handler->handle(new GetStudentFeeQuery(
            schoolId: $schoolId,
            studentFeeId: $studentFee,
        ));

        if ($dto === null) {
            return response()->json([
                'message' => 'Student fee not found.',
                'error_code' => 'finance.student_fee_not_found',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 404);
        }

        $this->securityAudit->record(
            SecurityEventType::FinanceDataAccess,
            'finance.student_fee.show',
            'viewed',
            $request->user(),
            'student_fee:'.$studentFee,
            [],
        );

        return response()->json([
            'data' => [
                'id' => $dto->id,
                'school_id' => $dto->schoolId,
                'enrollment_id' => $dto->enrollmentId,
                'fee_type_id' => $dto->feeTypeId,
                'academic_year_id' => $dto->academicYearId,
                'amount' => $dto->amount,
                'due_date' => $dto->dueDate,
                'status' => $dto->status,
                'created_at' => $dto->createdAt,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function cancel(
        int $studentFee,
        CancelStudentFeeRequest $request,
        CancelStudentFeeHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new CancelStudentFeeCommand(
            schoolId: $this->schoolContext->requireId(),
            studentFeeId: $studentFee,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            $code = $result->errors[0] ?? 'finance.student_fee_cancel_failed';

            return response()->json([
                'message' => 'Student fee cancel rejected.',
                'error_code' => $code,
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], $code === 'finance.student_fee_not_found' ? 404 : 422);
        }

        $this->securityAudit->record(
            SecurityEventType::FinanceDataModified,
            'finance.student_fee.cancel',
            'cancelled',
            $request->user(),
            'student_fee:'.$studentFee,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'student_fee_id' => $result->studentFeeId,
                'status' => StudentFeeStatus::Cancelled,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function reopen(
        int $studentFee,
        ReopenStudentFeeRequest $request,
        ReopenStudentFeeHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new ReopenStudentFeeCommand(
            schoolId: $this->schoolContext->requireId(),
            studentFeeId: $studentFee,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            $code = $result->errors[0] ?? 'finance.student_fee_reopen_failed';

            return response()->json([
                'message' => 'Student fee reopen rejected.',
                'error_code' => $code,
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], $code === 'finance.student_fee_not_found' ? 404 : 422);
        }

        $this->securityAudit->record(
            SecurityEventType::FinanceDataModified,
            'finance.student_fee.reopen',
            'reopened',
            $request->user(),
            'student_fee:'.$studentFee,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'student_fee_id' => $result->studentFeeId,
                'status' => StudentFeeStatus::Unpaid,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }
}
