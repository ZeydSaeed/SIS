<?php

namespace App\Http\Controllers\Api;

use App\Application\Promotion\Commands\CreatePromotionRuleCommand;
use App\Application\Promotion\Commands\CreatePromotionRuleHandler;
use App\Application\Promotion\Commands\DeactivatePromotionRuleCommand;
use App\Application\Promotion\Commands\DeactivatePromotionRuleHandler;
use App\Application\Promotion\Commands\ReactivatePromotionRuleCommand;
use App\Application\Promotion\Commands\ReactivatePromotionRuleHandler;
use App\Application\Promotion\Commands\RecordPromotionDecisionCommand;
use App\Application\Promotion\Commands\RecordPromotionDecisionHandler;
use App\Application\Promotion\DTOs\PromotionRecordDTO;
use App\Application\Promotion\DTOs\PromotionRuleDTO;
use App\Application\Promotion\Queries\GetPromotionRuleHandler;
use App\Application\Promotion\Queries\GetPromotionRuleQuery;
use App\Application\Promotion\Queries\ListPromotionRecordsHandler;
use App\Application\Promotion\Queries\ListPromotionRecordsQuery;
use App\Application\Promotion\Queries\ListPromotionRulesHandler;
use App\Application\Promotion\Queries\ListPromotionRulesQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Promotion\CreatePromotionRuleRequest;
use App\Http\Requests\Promotion\DeactivatePromotionRuleRequest;
use App\Http\Requests\Promotion\ReactivatePromotionRuleRequest;
use App\Http\Requests\Promotion\ListPromotionRecordsRequest;
use App\Http\Requests\Promotion\ListPromotionRulesRequest;
use App\Http\Requests\Promotion\RecordPromotionDecisionRequest;
use App\Http\Requests\Promotion\ShowPromotionRuleRequest;
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

    public function showRule(
        int $rule,
        ShowPromotionRuleRequest $request,
        GetPromotionRuleHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $dto = $handler->handle(new GetPromotionRuleQuery(
            schoolId: $schoolId,
            ruleId: $rule,
        ));

        if ($dto === null) {
            return response()->json([
                'message' => 'Promotion rule not found.',
                'error_code' => 'promotion.rule_not_found',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 404);
        }

        $this->securityAudit->record(
            SecurityEventType::PromotionDataAccess,
            'promotion.rule.show',
            'viewed',
            $request->user(),
            'promotion_rule:'.$rule,
            [],
        );

        return response()->json([
            'data' => [
                'id' => $dto->id,
                'school_id' => $dto->schoolId,
                'from_grade_level_id' => $dto->fromGradeLevelId,
                'to_grade_level_id' => $dto->toGradeLevelId,
                'min_gpa' => $dto->minGpa,
                'min_pass_subjects' => $dto->minPassSubjects,
                'max_failed_subjects' => $dto->maxFailedSubjects,
                'is_active' => $dto->isActive,
                'created_at' => $dto->createdAt,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function deactivateRule(
        int $rule,
        DeactivatePromotionRuleRequest $request,
        DeactivatePromotionRuleHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new DeactivatePromotionRuleCommand(
            schoolId: $this->schoolContext->requireId(),
            ruleId: $rule,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            $code = $result->errors[0] ?? 'promotion.rule_deactivate_failed';

            return response()->json([
                'message' => 'Promotion rule deactivate rejected.',
                'error_code' => $code,
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], $code === 'promotion.rule_not_found' ? 404 : 422);
        }

        $this->securityAudit->record(
            SecurityEventType::PromotionDataModified,
            'promotion.rule.deactivate',
            'deactivated',
            $request->user(),
            'promotion_rule:'.$rule,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'rule_id' => $result->ruleId,
                'is_active' => false,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function reactivateRule(
        int $rule,
        ReactivatePromotionRuleRequest $request,
        ReactivatePromotionRuleHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new ReactivatePromotionRuleCommand(
            schoolId: $this->schoolContext->requireId(),
            ruleId: $rule,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            $code = $result->errors[0] ?? 'promotion.rule_reactivate_failed';

            return response()->json([
                'message' => 'Promotion rule reactivate rejected.',
                'error_code' => $code,
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], $code === 'promotion.rule_not_found' ? 404 : 422);
        }

        $this->securityAudit->record(
            SecurityEventType::PromotionDataModified,
            'promotion.rule.reactivate',
            'reactivated',
            $request->user(),
            'promotion_rule:'.$rule,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'rule_id' => $result->ruleId,
                'is_active' => true,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
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
