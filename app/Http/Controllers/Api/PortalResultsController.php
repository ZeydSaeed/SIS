<?php

namespace App\Http\Controllers\Api;

use App\Application\Results\DTOs\IssuedTranscriptMetadataDTO;
use App\Application\Results\DTOs\OfficialTermResultDTO;
use App\Application\Results\Queries\GetIssuedTranscriptMetadataHandler;
use App\Application\Results\Queries\GetIssuedTranscriptMetadataQuery;
use App\Application\Results\Queries\GetOfficialAnnualResultHandler;
use App\Application\Results\Queries\GetOfficialAnnualResultQuery;
use App\Application\Results\Queries\GetOfficialTermResultHandler;
use App\Application\Results\Queries\GetOfficialTermResultQuery;
use App\Application\Results\Queries\GetOfficialYearGpaHandler;
use App\Application\Results\Queries\GetOfficialYearGpaQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\GetPortalIssuedTranscriptMetadataRequest;
use App\Http\Requests\Portal\GetPortalOfficialAnnualResultRequest;
use App\Http\Requests\Portal\GetPortalOfficialTermResultRequest;
use App\Http\Requests\Portal\GetPortalOfficialYearGpaRequest;
use App\Intelligence\Support\CorrelationContext;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Authorization\PortalPartyAccessService;
use App\Security\Context\SchoolContext;
use Illuminate\Http\JsonResponse;

/**
 * Student/guardian official-results portal (Phase 7.8). Ranking intentionally absent.
 */
class PortalResultsController extends Controller
{
    public function __construct(
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly SchoolContext $schoolContext,
        private readonly PortalPartyAccessService $portalAccess,
    ) {}

    public function officialTerm(
        GetPortalOfficialTermResultRequest $request,
        GetOfficialTermResultHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $enrollmentId = (int) $request->validated('enrollment_id');

        if ($denied = $this->denyUnlessOwnsEnrollment($request->user(), $enrollmentId, $schoolId, 'portal.results.term.show')) {
            return $denied;
        }

        $dto = $handler->handle(new GetOfficialTermResultQuery(
            schoolId: $schoolId,
            enrollmentId: $enrollmentId,
            academicYearId: (int) $request->validated('academic_year_id'),
            termId: (int) $request->validated('term_id'),
            subjectId: (int) $request->validated('subject_id'),
        ));

        if ($dto === null) {
            return $this->notFound('results.term_not_found');
        }

        $this->auditAccess($request->user(), 'portal.results.term.show', 'term_result:'.$dto->termResultId, [
            'academic_year_id' => $dto->academicYearId,
            'enrollment_id' => $dto->enrollmentId,
        ]);

        return response()->json([
            'data' => $this->termPayload($dto),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function officialAnnual(
        GetPortalOfficialAnnualResultRequest $request,
        GetOfficialAnnualResultHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $enrollmentId = (int) $request->validated('enrollment_id');

        if ($denied = $this->denyUnlessOwnsEnrollment($request->user(), $enrollmentId, $schoolId, 'portal.results.annual.show')) {
            return $denied;
        }

        $dto = $handler->handle(new GetOfficialAnnualResultQuery(
            schoolId: $schoolId,
            enrollmentId: $enrollmentId,
            academicYearId: (int) $request->validated('academic_year_id'),
        ));

        if ($dto === null) {
            return $this->notFound('results.annual_not_found');
        }

        $this->auditAccess($request->user(), 'portal.results.annual.show', 'annual_result:'.$dto->annualResultId, [
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
        GetPortalOfficialYearGpaRequest $request,
        GetOfficialYearGpaHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $enrollmentId = (int) $request->validated('enrollment_id');

        if ($denied = $this->denyUnlessOwnsEnrollment($request->user(), $enrollmentId, $schoolId, 'portal.results.gpa.show')) {
            return $denied;
        }

        $dto = $handler->handle(new GetOfficialYearGpaQuery(
            schoolId: $schoolId,
            enrollmentId: $enrollmentId,
            academicYearId: (int) $request->validated('academic_year_id'),
        ));

        if ($dto === null) {
            return $this->notFound('results.gpa_not_found');
        }

        $this->auditAccess($request->user(), 'portal.results.gpa.show', 'gpa_result:'.$dto->gpaResultId, [
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

    public function issuedTranscript(
        GetPortalIssuedTranscriptMetadataRequest $request,
        GetIssuedTranscriptMetadataHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $enrollmentId = (int) $request->validated('enrollment_id');

        if ($denied = $this->denyUnlessOwnsEnrollment($request->user(), $enrollmentId, $schoolId, 'portal.results.transcript.show')) {
            return $denied;
        }

        $dto = $handler->handle(new GetIssuedTranscriptMetadataQuery(
            schoolId: $schoolId,
            enrollmentId: $enrollmentId,
            academicYearId: (int) $request->validated('academic_year_id'),
        ));

        if ($dto === null) {
            return $this->notFound('results.transcript_not_found');
        }

        $this->auditAccess($request->user(), 'portal.results.transcript.show', 'transcript:'.$dto->transcriptId, [
            'academic_year_id' => $dto->academicYearId,
            'enrollment_id' => $dto->enrollmentId,
        ]);

        return response()->json([
            'data' => $this->transcriptPayload($dto),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    private function denyUnlessOwnsEnrollment(
        mixed $user,
        int $enrollmentId,
        int $schoolId,
        string $action,
    ): ?JsonResponse {
        if ($this->portalAccess->canAccessEnrollment($user, $enrollmentId, $schoolId)) {
            return null;
        }

        $this->securityAudit->record(
            SecurityEventType::IdorBlocked,
            $action,
            'denied',
            $user,
            'enrollment:'.$enrollmentId,
            [
                'school_id' => $schoolId,
                'enrollment_id' => $enrollmentId,
                'error_code' => 'portal.results.ownership_denied',
            ],
        );

        return $this->forbiddenOwnership();
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

    private function forbiddenOwnership(): JsonResponse
    {
        return response()->json([
            'message' => 'Portal ownership check failed for this enrollment.',
            'error_code' => 'portal.results.ownership_denied',
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ], 403);
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
