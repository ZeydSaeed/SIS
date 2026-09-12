<?php

namespace App\Http\Controllers\Api;

use App\Application\Results\DTOs\CurrentRankingSnapshotDTO;
use App\Application\Results\DTOs\IssuedTranscriptMetadataDTO;
use App\Application\Results\DTOs\OfficialTermResultDTO;
use App\Application\Results\DTOs\RankingSnapshotEntryDTO;
use App\Application\Results\Queries\GetCurrentRankingSnapshotHandler;
use App\Application\Results\Queries\GetCurrentRankingSnapshotQuery;
use App\Application\Results\Queries\GetIssuedTranscriptMetadataHandler;
use App\Application\Results\Queries\GetIssuedTranscriptMetadataQuery;
use App\Application\Results\Queries\GetOfficialAnnualResultHandler;
use App\Application\Results\Queries\GetOfficialAnnualResultQuery;
use App\Application\Results\Queries\GetOfficialTermResultHandler;
use App\Application\Results\Queries\GetOfficialTermResultQuery;
use App\Application\Results\Queries\GetOfficialYearGpaHandler;
use App\Application\Results\Queries\GetOfficialYearGpaQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Results\GetCurrentRankingSnapshotRequest;
use App\Http\Requests\Results\GetIssuedTranscriptMetadataRequest;
use App\Http\Requests\Results\GetOfficialAnnualResultRequest;
use App\Http\Requests\Results\GetOfficialTermResultRequest;
use App\Http\Requests\Results\GetOfficialYearGpaRequest;
use App\Intelligence\Support\CorrelationContext;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Http\JsonResponse;

class ResultsController extends Controller
{
    public function __construct(
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly SchoolContext $schoolContext,
    ) {}

    public function officialTerm(
        GetOfficialTermResultRequest $request,
        GetOfficialTermResultHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $dto = $handler->handle(new GetOfficialTermResultQuery(
            schoolId: $schoolId,
            enrollmentId: (int) $request->validated('enrollment_id'),
            academicYearId: (int) $request->validated('academic_year_id'),
            termId: (int) $request->validated('term_id'),
            subjectId: (int) $request->validated('subject_id'),
        ));

        if ($dto === null) {
            return $this->notFound('results.term_not_found');
        }

        $this->auditAccess($request->user(), 'results.term.show', 'term_result:'.$dto->termResultId, [
            'academic_year_id' => $dto->academicYearId,
            'enrollment_id' => $dto->enrollmentId,
        ]);

        return response()->json([
            'data' => $this->termPayload($dto),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function officialAnnual(
        GetOfficialAnnualResultRequest $request,
        GetOfficialAnnualResultHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $dto = $handler->handle(new GetOfficialAnnualResultQuery(
            schoolId: $schoolId,
            enrollmentId: (int) $request->validated('enrollment_id'),
            academicYearId: (int) $request->validated('academic_year_id'),
        ));

        if ($dto === null) {
            return $this->notFound('results.annual_not_found');
        }

        $this->auditAccess($request->user(), 'results.annual.show', 'annual_result:'.$dto->annualResultId, [
            'academic_year_id' => $dto->academicYearId,
            'enrollment_id' => $dto->enrollmentId,
        ]);

        return response()->json([
            'data' => [
                'school_id' => $dto->schoolId,
                'enrollment_id' => $dto->enrollmentId,
                'academic_year_id' => $dto->academicYearId,
                'annual_result_id' => $dto->annualResultId,
                'result_version' => $dto->resultVersion,
                'average_weighted_total' => $dto->averageWeightedTotal,
                'incomplete' => $dto->incomplete,
                'source_fingerprint' => $dto->sourceFingerprint,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function officialYearGpa(
        GetOfficialYearGpaRequest $request,
        GetOfficialYearGpaHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $dto = $handler->handle(new GetOfficialYearGpaQuery(
            schoolId: $schoolId,
            enrollmentId: (int) $request->validated('enrollment_id'),
            academicYearId: (int) $request->validated('academic_year_id'),
        ));

        if ($dto === null) {
            return $this->notFound('results.gpa_not_found');
        }

        $this->auditAccess($request->user(), 'results.gpa.show', 'gpa_result:'.$dto->gpaResultId, [
            'academic_year_id' => $dto->academicYearId,
            'enrollment_id' => $dto->enrollmentId,
        ]);

        return response()->json([
            'data' => [
                'school_id' => $dto->schoolId,
                'enrollment_id' => $dto->enrollmentId,
                'academic_year_id' => $dto->academicYearId,
                'gpa_result_id' => $dto->gpaResultId,
                'result_version' => $dto->resultVersion,
                'gpa_value' => $dto->gpaValue,
                'scale_code' => $dto->scaleCode,
                'incomplete' => $dto->incomplete,
                'source_fingerprint' => $dto->sourceFingerprint,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function currentRanking(
        GetCurrentRankingSnapshotRequest $request,
        GetCurrentRankingSnapshotHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $dto = $handler->handle(new GetCurrentRankingSnapshotQuery(
            schoolId: $schoolId,
            academicYearId: (int) $request->validated('academic_year_id'),
            classId: (int) $request->validated('class_id'),
        ));

        if ($dto === null) {
            return $this->notFound('results.ranking_not_found');
        }

        $this->auditAccess($request->user(), 'results.ranking.show', 'ranking_snapshot:'.$dto->rankingSnapshotId, [
            'academic_year_id' => $dto->academicYearId,
            'class_id' => $dto->classId,
        ]);

        return response()->json([
            'data' => $this->rankingPayload($dto),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function issuedTranscript(
        GetIssuedTranscriptMetadataRequest $request,
        GetIssuedTranscriptMetadataHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $dto = $handler->handle(new GetIssuedTranscriptMetadataQuery(
            schoolId: $schoolId,
            enrollmentId: (int) $request->validated('enrollment_id'),
            academicYearId: (int) $request->validated('academic_year_id'),
        ));

        if ($dto === null) {
            return $this->notFound('results.transcript_not_found');
        }

        $this->auditAccess($request->user(), 'results.transcript.show', 'transcript:'.$dto->transcriptId, [
            'academic_year_id' => $dto->academicYearId,
            'enrollment_id' => $dto->enrollmentId,
        ]);

        return response()->json([
            'data' => $this->transcriptPayload($dto),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function termPayload(OfficialTermResultDTO $dto): array
    {
        return [
            'school_id' => $dto->schoolId,
            'enrollment_id' => $dto->enrollmentId,
            'academic_year_id' => $dto->academicYearId,
            'term_id' => $dto->termId,
            'subject_id' => $dto->subjectId,
            'term_result_id' => $dto->termResultId,
            'result_version' => $dto->resultVersion,
            'weighted_total' => $dto->weightedTotal,
            'incomplete' => $dto->incomplete,
            'source_fingerprint' => $dto->sourceFingerprint,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rankingPayload(CurrentRankingSnapshotDTO $dto): array
    {
        return [
            'school_id' => $dto->schoolId,
            'academic_year_id' => $dto->academicYearId,
            'class_id' => $dto->classId,
            'ranking_snapshot_id' => $dto->rankingSnapshotId,
            'snapshot_version' => $dto->snapshotVersion,
            'metric_code' => $dto->metricCode,
            'participant_count' => $dto->participantCount,
            'comparative_projection_label' => $dto->comparativeProjectionLabel,
            'entries' => array_map(
                static fn (RankingSnapshotEntryDTO $e): array => [
                    'enrollment_id' => $e->enrollmentId,
                    'student_id' => $e->studentId,
                    'gpa_result_id' => $e->gpaResultId,
                    'metric_value' => $e->metricValue,
                    'rank_position' => $e->rankPosition,
                ],
                $dto->entries,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transcriptPayload(IssuedTranscriptMetadataDTO $dto): array
    {
        return [
            'school_id' => $dto->schoolId,
            'enrollment_id' => $dto->enrollmentId,
            'student_id' => $dto->studentId,
            'academic_year_id' => $dto->academicYearId,
            'transcript_id' => $dto->transcriptId,
            'transcript_version' => $dto->transcriptVersion,
            'transcript_number' => $dto->transcriptNumber,
            'payload_hash' => $dto->payloadHash,
            'storage_key' => $dto->storageKey,
            'issued_at' => $dto->issuedAt,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function auditAccess(mixed $user, string $action, string $resource, array $context): void
    {
        $this->securityAudit->record(
            SecurityEventType::ResultsDataAccess,
            $action,
            'viewed',
            $user,
            $resource,
            $context,
        );
    }

    private function notFound(string $errorCode): JsonResponse
    {
        return response()->json([
            'message' => 'Official current result not found.',
            'error_code' => $errorCode,
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ], 404);
    }
}
