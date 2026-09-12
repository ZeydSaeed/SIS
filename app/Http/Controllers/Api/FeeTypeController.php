<?php

namespace App\Http\Controllers\Api;

use App\Application\Finance\Commands\CreateFeeTypeCommand;
use App\Application\Finance\Commands\CreateFeeTypeHandler;
use App\Application\Finance\DTOs\FeeTypeDTO;
use App\Application\Finance\Queries\ListFeeTypesHandler;
use App\Application\Finance\Queries\ListFeeTypesQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\CreateFeeTypeRequest;
use App\Http\Requests\Finance\ListFeeTypesRequest;
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
}
