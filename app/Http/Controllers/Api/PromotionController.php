<?php

namespace App\Http\Controllers\Api;

use App\Application\Promotion\Commands\CreatePromotionRuleCommand;
use App\Application\Promotion\Commands\CreatePromotionRuleHandler;
use App\Application\Promotion\Commands\RecordPromotionDecisionCommand;
use App\Application\Promotion\Commands\RecordPromotionDecisionHandler;
use App\Application\Promotion\DTOs\PromotionRecordDTO;
use App\Application\Promotion\DTOs\PromotionRuleDTO;
use App\Application\Promotion\Queries\ListPromotionRecordsHandler;
use App\Application\Promotion\Queries\ListPromotionRecordsQuery;
use App\Application\Promotion\Queries\ListPromotionRulesHandler;
use App\Application\Promotion\Queries\ListPromotionRulesQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Promotion\CreatePromotionRuleRequest;
use App\Http\Requests\Promotion\ListPromotionRecordsRequest;
use App\Http\Requests\Promotion\ListPromotionRulesRequest;
use App\Http\Requests\Promotion\RecordPromotionDecisionRequest;
use App\Intelligence\Support\CorrelationContext;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Http\JsonResponse;

class PromotionController extends Controller
{
    public function __construct(
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly SchoolContext $schoolContext,
    ) {}

    public function storeRule(
        CreatePromotionRuleRequest $request,
        CreatePromotionRuleHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $result = $handler->handle(new CreatePromotionRuleCommand(
            schoolId: $schoolId,
            fromGradeLevelId: (int) $request->validated('from_grade_level_id'),
            toGradeLevelId: (int) $request->validated('to_grade_level_id'),
            minGpa: $request->validated('min_gpa') !== null ? (string) $request->validated('min_gpa') : null,
            minPassSubjects: $request->validated('min_pass_subjects') !== null
                ? (int) $request->validated('min_pass_subjects')
                : null,
            maxFailedSubjects: $request->validated('max_failed_subjects') !== null
                ? (int) $request->validated('max_failed_subjects')
                : null,
            isActive: (bool) ($request->validated('is_active') ?? true),
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            return response()->json([
                'message' => 'Promotion rule create rejected.',
                'error_code' => $result->errors[0] ?? 'promotion.rule_create_failed',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 422);
        }

        $this->securityAudit->record(
            SecurityEventType::PromotionDataModified,
            'promotion.rule.create',
            'created',
            $request->user(),
            'promotion_rule:'.$result->ruleId,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'rule_id' => $result->ruleId,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ], $result->fromIdempotencyCache ? 200 : 201);
    }

    public function indexRules(
        ListPromotionRulesRequest $request,
        ListPromotionRulesHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $activeOnly = $request->has('active_only')
            ? filter_var($request->validated('active_only'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            : null;

        $items = $handler->handle(new ListPromotionRulesQuery($schoolId, $activeOnly));

        $this->securityAudit->record(
            SecurityEventType::PromotionDataAccess,
            'promotion.rule.list',
            'listed',
            $request->user(),
            'school:'.$schoolId,
            ['count' => count($items)],
        );

        return response()->json([
            'data' => array_map(static fn (PromotionRuleDTO $dto): array => [
                'id' => $dto->id,
                'school_id' => $dto->schoolId,
                'from_grade_level_id' => $dto->fromGradeLevelId,
                'to_grade_level_id' => $dto->toGradeLevelId,
                'min_gpa' => $dto->minGpa,
                'min_pass_subjects' => $dto->minPassSubjects,
                'max_failed_subjects' => $dto->maxFailedSubjects,
                'is_active' => $dto->isActive,
                'created_at' => $dto->createdAt,
            ], $items),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function storeRecord(
        RecordPromotionDecisionRequest $request,
        RecordPromotionDecisionHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $result = $handler->handle(new RecordPromotionDecisionCommand(
            schoolId: $schoolId,
            enrollmentId: (int) $request->validated('enrollment_id'),
            academicYearId: (int) $request->validated('academic_year_id'),
            toGradeLevelId: (int) $request->validated('to_grade_level_id'),
            promotionStatus: (int) $request->validated('promotion_status'),
            gpaAtPromotion: $request->validated('gpa_at_promotion') !== null
                ? (string) $request->validated('gpa_at_promotion')
                : null,
            notes: $request->validated('notes'),
            decidedBy: $request->user()?->id,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            return response()->json([
                'message' => 'Promotion decision rejected.',
                'error_code' => $result->errors[0] ?? 'promotion.decision_failed',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 422);
        }

        $this->securityAudit->record(
            SecurityEventType::PromotionDataModified,
            'promotion.record.create',
            'recorded',
            $request->user(),
            'promotion_record:'.$result->recordId,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'record_id' => $result->recordId,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ], $result->fromIdempotencyCache ? 200 : 201);
    }

    public function indexRecords(
        ListPromotionRecordsRequest $request,
        ListPromotionRecordsHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $items = $handler->handle(new ListPromotionRecordsQuery(
            schoolId: $schoolId,
            academicYearId: (int) $request->validated('academic_year_id'),
        ));

        $this->securityAudit->record(
            SecurityEventType::PromotionDataAccess,
            'promotion.record.list',
            'listed',
            $request->user(),
            'school:'.$schoolId,
            ['count' => count($items)],
        );

        return response()->json([
            'data' => array_map(static fn (PromotionRecordDTO $dto): array => [
                'id' => $dto->id,
                'school_id' => $dto->schoolId,
                'enrollment_id' => $dto->enrollmentId,
                'academic_year_id' => $dto->academicYearId,
                'from_grade_level_id' => $dto->fromGradeLevelId,
                'to_grade_level_id' => $dto->toGradeLevelId,
                'promotion_status' => $dto->promotionStatus,
                'gpa_at_promotion' => $dto->gpaAtPromotion,
                'decided_by' => $dto->decidedBy,
                'decided_at' => $dto->decidedAt,
                'notes' => $dto->notes,
                'created_at' => $dto->createdAt,
            ], $items),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }
}
