<?php

namespace App\Http\Controllers\Api;

use App\Application\Results\Commands\BuildRankingSnapshotCommand;
use App\Application\Results\Commands\BuildRankingSnapshotHandler;
use App\Application\Results\Commands\CalculateAnnualResultCommand;
use App\Application\Results\Commands\CalculateAnnualResultHandler;
use App\Application\Results\Commands\CalculateGpaCommand;
use App\Application\Results\Commands\CalculateGpaHandler;
use App\Application\Results\Commands\CalculateTermResultCommand;
use App\Application\Results\Commands\CalculateTermResultHandler;
use App\Application\Results\Commands\FinalizeAnnualResultCommand;
use App\Application\Results\Commands\FinalizeAnnualResultHandler;
use App\Application\Results\Commands\FinalizeGpaCommand;
use App\Application\Results\Commands\FinalizeGpaHandler;
use App\Application\Results\Commands\FinalizeTermResultCommand;
use App\Application\Results\Commands\FinalizeTermResultHandler;
use App\Application\Results\Commands\IssueTranscriptCommand;
use App\Application\Results\Commands\IssueTranscriptHandler;
use App\Application\Results\Commands\RebuildAnnualResultCommand;
use App\Application\Results\Commands\RebuildAnnualResultHandler;
use App\Application\Results\Commands\RebuildGpaCommand;
use App\Application\Results\Commands\RebuildGpaHandler;
use App\Application\Results\Commands\RebuildTermResultCommand;
use App\Application\Results\Commands\RebuildTermResultHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\Results\BuildRankingSnapshotRequest;
use App\Http\Requests\Results\CalculateAnnualResultRequest;
use App\Http\Requests\Results\CalculateGpaRequest;
use App\Http\Requests\Results\CalculateTermResultRequest;
use App\Http\Requests\Results\FinalizeAnnualResultRequest;
use App\Http\Requests\Results\FinalizeGpaRequest;
use App\Http\Requests\Results\FinalizeTermResultRequest;
use App\Http\Requests\Results\IssueTranscriptRequest;
use App\Http\Requests\Results\RebuildAnnualResultRequest;
use App\Http\Requests\Results\RebuildGpaRequest;
use App\Http\Requests\Results\RebuildTermResultRequest;
use App\Intelligence\Support\CorrelationContext;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Http\JsonResponse;

class ResultsWriteController extends Controller
{
    public function __construct(
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly SchoolContext $schoolContext,
    ) {}

    public function calculateTerm(
        CalculateTermResultRequest $request,
        CalculateTermResultHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new CalculateTermResultCommand(
            schoolId: $this->schoolContext->requireId(),
            enrollmentId: (int) $request->validated('enrollment_id'),
            academicYearId: (int) $request->validated('academic_year_id'),
            termId: (int) $request->validated('term_id'),
            subjectId: (int) $request->validated('subject_id'),
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
            createdBy: $request->user()?->id,
            correlationId: CorrelationContext::id(),
        ));

        $this->audit($request->user(), 'results.term.calculate', 'calculated', 'term_result:'.($result->termResultId ?? 'unknown'));

        return response()->json([
            'data' => [
                'id' => $result->termResultId,
                'result_version' => $result->resultVersion,
                'weighted_total' => $result->weightedTotal,
                'incomplete' => $result->incomplete,
            ],
            'meta' => $this->meta($result->fromIdempotencyCache),
        ], $result->fromIdempotencyCache ? 200 : 201);
    }

    public function finalizeTerm(
        FinalizeTermResultRequest $request,
        FinalizeTermResultHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new FinalizeTermResultCommand(
            schoolId: $this->schoolContext->requireId(),
            enrollmentId: (int) $request->validated('enrollment_id'),
            academicYearId: (int) $request->validated('academic_year_id'),
            termId: (int) $request->validated('term_id'),
            subjectId: (int) $request->validated('subject_id'),
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
            createdBy: $request->user()?->id,
            correlationId: CorrelationContext::id(),
        ));

        $this->audit($request->user(), 'results.term.finalize', 'finalized', 'term_result:'.($result->termResultId ?? 'unknown'));

        return response()->json([
            'data' => [
                'id' => $result->termResultId,
                'result_version' => $result->resultVersion,
                'weighted_total' => $result->weightedTotal,
                'incomplete' => $result->incomplete,
            ],
            'meta' => $this->meta($result->fromIdempotencyCache),
        ]);
    }

    public function calculateAnnual(
        CalculateAnnualResultRequest $request,
        CalculateAnnualResultHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new CalculateAnnualResultCommand(
            schoolId: $this->schoolContext->requireId(),
            enrollmentId: (int) $request->validated('enrollment_id'),
            academicYearId: (int) $request->validated('academic_year_id'),
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
            createdBy: $request->user()?->id,
            correlationId: CorrelationContext::id(),
        ));

        $this->audit($request->user(), 'results.annual.calculate', 'calculated', 'annual_result:'.($result->annualResultId ?? 'unknown'));

        return response()->json([
            'data' => [
                'id' => $result->annualResultId,
                'result_version' => $result->resultVersion,
                'average_weighted_total' => $result->averageWeightedTotal,
                'incomplete' => $result->incomplete,
            ],
            'meta' => $this->meta($result->fromIdempotencyCache),
        ], $result->fromIdempotencyCache ? 200 : 201);
    }

    public function finalizeAnnual(
        FinalizeAnnualResultRequest $request,
        FinalizeAnnualResultHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new FinalizeAnnualResultCommand(
            schoolId: $this->schoolContext->requireId(),
            enrollmentId: (int) $request->validated('enrollment_id'),
            academicYearId: (int) $request->validated('academic_year_id'),
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
            createdBy: $request->user()?->id,
            correlationId: CorrelationContext::id(),
        ));

        $this->audit($request->user(), 'results.annual.finalize', 'finalized', 'annual_result:'.($result->annualResultId ?? 'unknown'));

        return response()->json([
            'data' => [
                'id' => $result->annualResultId,
                'result_version' => $result->resultVersion,
                'average_weighted_total' => $result->averageWeightedTotal,
                'incomplete' => $result->incomplete,
            ],
            'meta' => $this->meta($result->fromIdempotencyCache),
        ]);
    }

    public function calculateGpa(
        CalculateGpaRequest $request,
        CalculateGpaHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new CalculateGpaCommand(
            schoolId: $this->schoolContext->requireId(),
            enrollmentId: (int) $request->validated('enrollment_id'),
            academicYearId: (int) $request->validated('academic_year_id'),
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
            createdBy: $request->user()?->id,
            correlationId: CorrelationContext::id(),
        ));

        $this->audit($request->user(), 'results.gpa.calculate', 'calculated', 'gpa_result:'.($result->gpaResultId ?? 'unknown'));

        return response()->json([
            'data' => [
                'id' => $result->gpaResultId,
                'result_version' => $result->resultVersion,
                'gpa_value' => $result->gpaValue,
                'scale_code' => $result->scaleCode,
                'incomplete' => $result->incomplete,
            ],
            'meta' => $this->meta($result->fromIdempotencyCache),
        ], $result->fromIdempotencyCache ? 200 : 201);
    }

    public function finalizeGpa(
        FinalizeGpaRequest $request,
        FinalizeGpaHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new FinalizeGpaCommand(
            schoolId: $this->schoolContext->requireId(),
            enrollmentId: (int) $request->validated('enrollment_id'),
            academicYearId: (int) $request->validated('academic_year_id'),
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
            createdBy: $request->user()?->id,
            correlationId: CorrelationContext::id(),
        ));

        $this->audit($request->user(), 'results.gpa.finalize', 'finalized', 'gpa_result:'.($result->gpaResultId ?? 'unknown'));

        return response()->json([
            'data' => [
                'id' => $result->gpaResultId,
                'result_version' => $result->resultVersion,
                'gpa_value' => $result->gpaValue,
                'scale_code' => $result->scaleCode,
                'incomplete' => $result->incomplete,
            ],
            'meta' => $this->meta($result->fromIdempotencyCache),
        ]);
    }

    public function buildRanking(
        BuildRankingSnapshotRequest $request,
        BuildRankingSnapshotHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new BuildRankingSnapshotCommand(
            schoolId: $this->schoolContext->requireId(),
            academicYearId: (int) $request->validated('academic_year_id'),
            classId: (int) $request->validated('class_id'),
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
            createdBy: $request->user()?->id,
            correlationId: CorrelationContext::id(),
        ));

        $this->audit($request->user(), 'results.ranking.build', 'built', 'ranking_snapshot:'.($result->rankingSnapshotId ?? 'unknown'));

        return response()->json([
            'data' => [
                'id' => $result->rankingSnapshotId,
                'snapshot_version' => $result->snapshotVersion,
                'participant_count' => $result->participantCount,
            ],
            'meta' => $this->meta($result->fromIdempotencyCache),
        ], $result->fromIdempotencyCache ? 200 : 201);
    }

    public function issueTranscript(
        IssueTranscriptRequest $request,
        IssueTranscriptHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new IssueTranscriptCommand(
            schoolId: $this->schoolContext->requireId(),
            enrollmentId: (int) $request->validated('enrollment_id'),
            academicYearId: (int) $request->validated('academic_year_id'),
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
            storageKey: $request->validated('storage_key'),
            issuedBy: $request->user()?->id,
            correlationId: CorrelationContext::id(),
        ));

        $this->audit($request->user(), 'results.transcript.issue', 'issued', 'transcript:'.($result->transcriptId ?? 'unknown'));

        return response()->json([
            'data' => [
                'id' => $result->transcriptId,
                'transcript_version' => $result->transcriptVersion,
                'transcript_number' => $result->transcriptNumber,
                'payload_hash' => $result->payloadHash,
            ],
            'meta' => $this->meta($result->fromIdempotencyCache),
        ], $result->fromIdempotencyCache ? 200 : 201);
    }

    public function rebuildTerm(
        RebuildTermResultRequest $request,
        RebuildTermResultHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new RebuildTermResultCommand(
            schoolId: $this->schoolContext->requireId(),
            enrollmentId: (int) $request->validated('enrollment_id'),
            academicYearId: (int) $request->validated('academic_year_id'),
            termId: (int) $request->validated('term_id'),
            subjectId: (int) $request->validated('subject_id'),
            mode: (string) $request->validated('mode'),
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
            createdBy: $request->user()?->id,
            correlationId: CorrelationContext::id(),
        ));

        $this->audit($request->user(), 'results.term.rebuild', 'rebuilt', 'term_result:'.($result->termResultId ?? 'unknown'));

        return response()->json([
            'data' => [
                'id' => $result->termResultId,
                'result_version' => $result->resultVersion,
                'weighted_total' => $result->weightedTotal,
                'incomplete' => $result->incomplete,
                'unchanged' => $result->unchanged,
                'mode' => $result->mode,
            ],
            'meta' => $this->meta($result->fromIdempotencyCache),
        ]);
    }

    public function rebuildAnnual(
        RebuildAnnualResultRequest $request,
        RebuildAnnualResultHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new RebuildAnnualResultCommand(
            schoolId: $this->schoolContext->requireId(),
            enrollmentId: (int) $request->validated('enrollment_id'),
            academicYearId: (int) $request->validated('academic_year_id'),
            mode: (string) $request->validated('mode'),
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
            createdBy: $request->user()?->id,
            correlationId: CorrelationContext::id(),
        ));

        $this->audit($request->user(), 'results.annual.rebuild', 'rebuilt', 'annual_result:'.($result->annualResultId ?? 'unknown'));

        return response()->json([
            'data' => [
                'id' => $result->annualResultId,
                'result_version' => $result->resultVersion,
                'average_weighted_total' => $result->averageWeightedTotal,
                'incomplete' => $result->incomplete,
                'unchanged' => $result->unchanged,
                'mode' => $result->mode,
            ],
            'meta' => $this->meta($result->fromIdempotencyCache),
        ]);
    }

    public function rebuildGpa(
        RebuildGpaRequest $request,
        RebuildGpaHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new RebuildGpaCommand(
            schoolId: $this->schoolContext->requireId(),
            enrollmentId: (int) $request->validated('enrollment_id'),
            academicYearId: (int) $request->validated('academic_year_id'),
            mode: (string) $request->validated('mode'),
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
            createdBy: $request->user()?->id,
            correlationId: CorrelationContext::id(),
        ));

        $this->audit($request->user(), 'results.gpa.rebuild', 'rebuilt', 'gpa_result:'.($result->gpaResultId ?? 'unknown'));

        return response()->json([
            'data' => [
                'id' => $result->gpaResultId,
                'result_version' => $result->resultVersion,
                'gpa_value' => $result->gpaValue,
                'incomplete' => $result->incomplete,
                'unchanged' => $result->unchanged,
                'mode' => $result->mode,
            ],
            'meta' => $this->meta($result->fromIdempotencyCache),
        ]);
    }

    /**
     * @return array{from_idempotency_cache: bool, correlation_id: string|null}
     */
    private function meta(bool $fromIdempotencyCache): array
    {
        return [
            'from_idempotency_cache' => $fromIdempotencyCache,
            'correlation_id' => CorrelationContext::id(),
        ];
    }

    private function audit(mixed $user, string $action, string $outcome, string $resource): void
    {
        $this->securityAudit->record(
            SecurityEventType::ResultsDataModified,
            $action,
            $outcome,
            $user,
            $resource,
            [],
        );
    }
}
