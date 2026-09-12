<?php

namespace App\Http\Controllers\Api;

use App\Application\Finance\DTOs\FinanceTransactionDTO;
use App\Application\Finance\Queries\ListFinanceTransactionsHandler;
use App\Application\Finance\Queries\ListFinanceTransactionsQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ListFinanceTransactionsRequest;
use App\Intelligence\Support\CorrelationContext;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Http\JsonResponse;

class FinanceTransactionController extends Controller
{
    public function __construct(
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly SchoolContext $schoolContext,
    ) {}

    public function index(
        ListFinanceTransactionsRequest $request,
        ListFinanceTransactionsHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $studentId = $request->validated('student_id');
        $academicYearId = $request->validated('academic_year_id');

        $items = $handler->handle(new ListFinanceTransactionsQuery(
            schoolId: $schoolId,
            studentId: $studentId !== null ? (int) $studentId : null,
            academicYearId: $academicYearId !== null ? (int) $academicYearId : null,
        ));

        $this->securityAudit->record(
            SecurityEventType::FinanceDataAccess,
            'finance.transaction.list',
            'listed',
            $request->user(),
            'school:'.$schoolId,
            ['count' => count($items)],
        );

        return response()->json([
            'data' => array_map(static fn (FinanceTransactionDTO $dto): array => [
                'id' => $dto->id,
                'school_id' => $dto->schoolId,
                'student_id' => $dto->studentId,
                'academic_year_id' => $dto->academicYearId,
                'transaction_type' => $dto->transactionType,
                'amount' => $dto->amount,
                'balance_after' => $dto->balanceAfter,
                'reference_type' => $dto->referenceType,
                'reference_id' => $dto->referenceId,
                'notes' => $dto->notes,
                'created_by' => $dto->createdBy,
                'created_at' => $dto->createdAt,
            ], $items),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }
}
