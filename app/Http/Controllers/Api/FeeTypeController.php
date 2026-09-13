<?php

namespace App\Http\Controllers\Api;

use App\Application\Finance\Commands\CreateFeeTypeCommand;
use App\Application\Finance\Commands\CreateFeeTypeHandler;
use App\Application\Finance\Commands\DeactivateFeeTypeCommand;
use App\Application\Finance\Commands\DeactivateFeeTypeHandler;
use App\Application\Finance\Commands\ReactivateFeeTypeCommand;
use App\Application\Finance\Commands\ReactivateFeeTypeHandler;
use App\Application\Finance\DTOs\FeeTypeDTO;
use App\Application\Finance\Queries\ListFeeTypesHandler;
use App\Application\Finance\Queries\ListFeeTypesQuery;
use App\Domain\Finance\ValueObjects\FeeTypeStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\CreateFeeTypeRequest;
use App\Http\Requests\Finance\DeactivateFeeTypeRequest;
use App\Http\Requests\Finance\ListFeeTypesRequest;
use App\Http\Requests\Finance\ReactivateFeeTypeRequest;
use App\Intelligence\Support\CorrelationContext;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Http\JsonResponse;

class FeeTypeController extends Controller
{
    public function __construct(
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly SchoolContext $schoolContext,
    ) {}

    public function store(
        CreateFeeTypeRequest $request,
        CreateFeeTypeHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $result = $handler->handle(new CreateFeeTypeCommand(
            schoolId: $schoolId,
            code: (string) $request->validated('code'),
            name: (string) $request->validated('name'),
            amount: (string) $request->validated('amount'),
            isRecurring: (bool) $request->boolean('is_recurring'),
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            return response()->json([
                'message' => 'Fee type create rejected.',
                'error_code' => $result->errors[0] ?? 'finance.fee_type_create_failed',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 422);
        }

        $this->securityAudit->record(
            SecurityEventType::FinanceDataModified,
            'finance.fee_type.create',
            'created',
            $request->user(),
            'fee_type:'.$result->feeTypeId,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'fee_type_id' => $result->feeTypeId,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ], $result->fromIdempotencyCache ? 200 : 201);
    }

    public function index(
        ListFeeTypesRequest $request,
        ListFeeTypesHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $status = $request->validated('fee_status');
        $items = $handler->handle(new ListFeeTypesQuery(
            schoolId: $schoolId,
            status: $status !== null ? (int) $status : null,
        ));

        $this->securityAudit->record(
            SecurityEventType::FinanceDataAccess,
            'finance.fee_type.list',
            'listed',
            $request->user(),
            'school:'.$schoolId,
            ['count' => count($items)],
        );

        return response()->json([
            'data' => array_map(static fn (FeeTypeDTO $dto): array => [
                'id' => $dto->id,
                'school_id' => $dto->schoolId,
                'code' => $dto->code,
                'name' => $dto->name,
                'amount' => $dto->amount,
                'is_recurring' => $dto->isRecurring,
                'status' => $dto->status,
                'created_at' => $dto->createdAt,
            ], $items),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function deactivate(
        int $feeType,
        DeactivateFeeTypeRequest $request,
        DeactivateFeeTypeHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new DeactivateFeeTypeCommand(
            schoolId: $this->schoolContext->requireId(),
            feeTypeId: $feeType,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            $code = $result->errors[0] ?? 'finance.fee_type_deactivate_failed';

            return response()->json([
                'message' => 'Fee type deactivate rejected.',
                'error_code' => $code,
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], $code === 'finance.fee_type_not_found' ? 404 : 422);
        }

        $this->securityAudit->record(
            SecurityEventType::FinanceDataModified,
            'finance.fee_type.deactivate',
            'deactivated',
            $request->user(),
            'fee_type:'.$feeType,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'fee_type_id' => $result->feeTypeId,
                'status' => FeeTypeStatus::Inactive,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function reactivate(
        int $feeType,
        ReactivateFeeTypeRequest $request,
        ReactivateFeeTypeHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new ReactivateFeeTypeCommand(
            schoolId: $this->schoolContext->requireId(),
            feeTypeId: $feeType,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            $code = $result->errors[0] ?? 'finance.fee_type_reactivate_failed';

            return response()->json([
                'message' => 'Fee type reactivate rejected.',
                'error_code' => $code,
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], $code === 'finance.fee_type_not_found' ? 404 : 422);
        }

        $this->securityAudit->record(
            SecurityEventType::FinanceDataModified,
            'finance.fee_type.reactivate',
            'reactivated',
            $request->user(),
            'fee_type:'.$feeType,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'fee_type_id' => $result->feeTypeId,
                'status' => FeeTypeStatus::Active,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }
}
