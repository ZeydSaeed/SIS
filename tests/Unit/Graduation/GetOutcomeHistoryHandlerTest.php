<?php

namespace Tests\Unit\Graduation;

use App\Application\Graduation\Contracts\GraduationAuthorityPort;
use App\Application\Graduation\Contracts\GraduationReadRepositoryInterface;
use App\Application\Graduation\DTOs\OutcomeHistoryApprovalRefDTO;
use App\Application\Graduation\DTOs\OutcomeHistoryAwardDTO;
use App\Application\Graduation\DTOs\OutcomeHistoryAwardVersionDTO;
use App\Application\Graduation\DTOs\OutcomeHistoryCompletionDTO;
use App\Application\Graduation\DTOs\OutcomeHistoryCompletionVersionDTO;
use App\Application\Graduation\DTOs\OutcomeHistoryDTO;
use App\Application\Graduation\DTOs\OutcomeHistoryEvaluationRefDTO;
use App\Application\Graduation\Queries\GetOutcomeHistoryHandler;
use App\Application\Graduation\Queries\GetOutcomeHistoryQuery;
use App\Domain\Graduation\Exceptions\GraduationAuthorityDeniedException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GetOutcomeHistoryHandlerTest extends TestCase
{
    private function evaluationRef(int $id = 1): OutcomeHistoryEvaluationRefDTO
    {
        return new OutcomeHistoryEvaluationRefDTO(
            evaluationId: $id,
            requirementDefinitionVersionId: 10 + $id,
            resultStatus: 1,
        );
    }

    private function approvalRef(int $id = 1, int $attemptNo = 1): OutcomeHistoryApprovalRefDTO
    {
        return new OutcomeHistoryApprovalRefDTO(
            approvalId: $id,
            attemptNo: $attemptNo,
            decisionStatus: 2,
        );
    }

    private function completionVersion(
        int $versionId,
        int $versionNo,
        bool $isCurrentOfficial = false,
        array $evaluationRefs = [],
        array $approvalRefs = [],
        ?int $supersedes = null,
        ?int $supersededBy = null,
    ): OutcomeHistoryCompletionVersionDTO {
        return new OutcomeHistoryCompletionVersionDTO(
            versionId: $versionId,
            versionNo: $versionNo,
            lifecycleStatus: 1,
            evaluationStatus: 2,
            eligibilityStatus: 2,
            isCurrentOfficial: $isCurrentOfficial,
            eligibilityPolicyVersionId: 99,
            calculationVersion: 'calc-h',
            sourceFingerprint: null,
            policyFingerprint: null,
            evaluatedAt: '2026-01-01 00:00:00+00',
            supersedesVersionId: $supersedes,
            supersededByVersionId: $supersededBy,
            correlationId: 'c-v'.$versionNo,
            createdAt: '2026-01-01 00:00:00+00',
            evaluationRefs: $evaluationRefs,
            approvalRefs: $approvalRefs,
        );
    }

    private function awardVersion(
        int $versionId,
        int $versionNo,
        int $lifecycleStatus = 1,
        bool $isCurrentIssued = true,
        ?int $supersedes = null,
        ?int $supersededBy = null,
    ): OutcomeHistoryAwardVersionDTO {
        return new OutcomeHistoryAwardVersionDTO(
            versionId: $versionId,
            versionNo: $versionNo,
            graduationApprovalId: 30,
            completionOutcomeVersionId: 20,
            lifecycleStatus: $lifecycleStatus,
            isCurrentIssued: $isCurrentIssued,
            awardedAt: '2026-01-01 00:00:00+00',
            issuedBy: 8,
            awardNumber: null,
            honorsCode: null,
            supersedesVersionId: $supersedes,
            supersededByVersionId: $supersededBy,
            correlationId: 'a-v'.$versionNo,
            createdAt: '2026-01-01 00:00:00+00',
        );
    }

    private function historyDto(
        ?OutcomeHistoryCompletionDTO $completion = null,
        ?OutcomeHistoryAwardDTO $award = null,
    ): OutcomeHistoryDTO {
        return new OutcomeHistoryDTO(
            schoolId: 1,
            enrollmentId: 10,
            completionOutcome: $completion,
            award: $award,
        );
    }

    #[Test]
    public function returns_historical_aggregate_from_repository(): void
    {
        $completion = new OutcomeHistoryCompletionDTO(
            completionOutcomeId: 100,
            studentId: 5,
            academicYearId: 9,
            specializationId: null,
            currentOfficialVersionId: null,
            createdBy: 7,
            createdAt: '2026-01-01 00:00:00+00',
            versions: [
                $this->completionVersion(1, 1, false, [$this->evaluationRef(1)], [$this->approvalRef(1, 1)]),
                $this->completionVersion(2, 2, true, [$this->evaluationRef(2)], [
                    $this->approvalRef(2, 1),
                    $this->approvalRef(3, 2),
                ]),
            ],
        );
        $award = new OutcomeHistoryAwardDTO(
            awardId: 200,
            studentId: 5,
            academicYearId: 9,
            specializationId: null,
            currentIssuedVersionId: 50,
            createdBy: 8,
            createdAt: '2026-01-01 00:00:00+00',
            versions: [
                $this->awardVersion(40, 1, 3, false),
                $this->awardVersion(50, 2, 1, true),
            ],
        );

        $repo = $this->createMock(GraduationReadRepositoryInterface::class);
        $repo->method('findOutcomeHistory')->with(1, 10)->willReturn($this->historyDto($completion, $award));

        $authority = $this->createMock(GraduationAuthorityPort::class);
        $authority->expects($this->once())->method('assertSchoolMatches')->with(1);

        $handler = new GetOutcomeHistoryHandler($repo, $authority);
        $result = $handler->handle(new GetOutcomeHistoryQuery(1, 10));

        $this->assertSame(1, $result->schoolId);
        $this->assertSame(10, $result->enrollmentId);
        $this->assertNotNull($result->completionOutcome);
        $this->assertCount(2, $result->completionOutcome->versions);
        $this->assertSame([1, 2], array_map(
            static fn ($v) => $v->versionNo,
            $result->completionOutcome->versions,
        ));
        $this->assertFalse($result->completionOutcome->versions[0]->isCurrentOfficial);
        $this->assertTrue($result->completionOutcome->versions[1]->isCurrentOfficial);
        $this->assertCount(2, $result->completionOutcome->versions[1]->approvalRefs);
        $this->assertSame([1, 2], array_map(
            static fn ($a) => $a->attemptNo,
            $result->completionOutcome->versions[1]->approvalRefs,
        ));
        $this->assertNotNull($result->award);
        $this->assertCount(2, $result->award->versions);
        $this->assertSame(3, $result->award->versions[0]->lifecycleStatus);
        $this->assertFalse($result->award->versions[0]->isCurrentIssued);
    }

    #[Test]
    public function returns_empty_aggregate_when_neither_completion_nor_award_exists(): void
    {
        $repo = $this->createMock(GraduationReadRepositoryInterface::class);
        $repo->method('findOutcomeHistory')->willReturn($this->historyDto(null, null));

        $authority = $this->createMock(GraduationAuthorityPort::class);
        $authority->method('assertSchoolMatches');

        $handler = new GetOutcomeHistoryHandler($repo, $authority);
        $result = $handler->handle(new GetOutcomeHistoryQuery(1, 10));

        $this->assertNull($result->completionOutcome);
        $this->assertNull($result->award);
    }

    #[Test]
    public function missing_completion_may_still_return_award(): void
    {
        $award = new OutcomeHistoryAwardDTO(
            awardId: 200,
            studentId: 5,
            academicYearId: 9,
            specializationId: null,
            currentIssuedVersionId: 50,
            createdBy: 8,
            createdAt: '2026-01-01 00:00:00+00',
            versions: [$this->awardVersion(50, 1)],
        );

        $repo = $this->createMock(GraduationReadRepositoryInterface::class);
        $repo->method('findOutcomeHistory')->willReturn($this->historyDto(null, $award));

        $authority = $this->createMock(GraduationAuthorityPort::class);
        $authority->method('assertSchoolMatches');

        $handler = new GetOutcomeHistoryHandler($repo, $authority);
        $result = $handler->handle(new GetOutcomeHistoryQuery(1, 10));

        $this->assertNull($result->completionOutcome);
        $this->assertNotNull($result->award);
        $this->assertSame(200, $result->award->awardId);
    }

    #[Test]
    public function does_not_expose_synthetic_student_status_or_sod_fields(): void
    {
        $completion = new OutcomeHistoryCompletionDTO(
            completionOutcomeId: 100,
            studentId: 5,
            academicYearId: 9,
            specializationId: null,
            currentOfficialVersionId: null,
            createdBy: null,
            createdAt: '2026-01-01 00:00:00+00',
            versions: [
                $this->completionVersion(1, 1, false, [$this->evaluationRef()], [$this->approvalRef()]),
            ],
        );
        $award = new OutcomeHistoryAwardDTO(
            awardId: 200,
            studentId: 5,
            academicYearId: 9,
            specializationId: null,
            currentIssuedVersionId: 50,
            createdBy: 8,
            createdAt: '2026-01-01 00:00:00+00',
            versions: [$this->awardVersion(50, 1)],
        );

        $payload = $this->historyDto($completion, $award)->toArray();

        $this->assertArrayNotHasKey('student_status', $payload);
        $this->assertArrayNotHasKey('is_graduated', $payload);
        $this->assertArrayNotHasKey('graduated', $payload);
        $this->assertArrayNotHasKey('graduation_status', $payload);
        $this->assertArrayNotHasKey('sod_valid', $payload);
        $this->assertArrayNotHasKey('award_status_label', $payload);

        $version = $payload['completion_outcome']['versions'][0];
        $this->assertArrayHasKey('is_current_official', $version);
        $this->assertArrayHasKey('evaluation_refs', $version);
        $this->assertArrayHasKey('approval_refs', $version);
        $this->assertArrayNotHasKey('status_label', $version);
        $this->assertArrayNotHasKey('evaluations', $version);
        $this->assertArrayNotHasKey('evidence_sets', $version);
        $this->assertArrayNotHasKey('evidence_items', $version);

        $evalRef = $version['evaluation_refs'][0];
        $this->assertArrayHasKey('evaluation_id', $evalRef);
        $this->assertArrayHasKey('requirement_definition_version_id', $evalRef);
        $this->assertArrayHasKey('result_status', $evalRef);
        $this->assertArrayNotHasKey('notes_ref', $evalRef);
        $this->assertArrayNotHasKey('evaluated_at', $evalRef);

        $awardVersion = $payload['award']['versions'][0];
        $this->assertArrayNotHasKey('is_graduated', $awardVersion);
        $this->assertArrayNotHasKey('award_status_label', $awardVersion);
        $this->assertArrayHasKey('lifecycle_status', $awardVersion);
        $this->assertArrayHasKey('is_current_issued', $awardVersion);
    }

    #[Test]
    public function exposes_supersession_columns_as_stored_without_reconstruction(): void
    {
        $version = $this->completionVersion(
            versionId: 2,
            versionNo: 2,
            supersedes: null,
            supersededBy: null,
        )->toArray();

        $this->assertNull($version['supersedes_version_id']);
        $this->assertNull($version['superseded_by_version_id']);
        $this->assertArrayNotHasKey('reconstructed_chain', $version);
        $this->assertArrayNotHasKey('supersession_edges', $version);
    }

    #[Test]
    public function school_mismatch_rejects_before_repository_access(): void
    {
        $repo = $this->createMock(GraduationReadRepositoryInterface::class);
        $repo->expects($this->never())->method('findOutcomeHistory');

        $authority = $this->createMock(GraduationAuthorityPort::class);
        $authority->method('assertSchoolMatches')
            ->willThrowException(GraduationAuthorityDeniedException::schoolMismatch());

        $handler = new GetOutcomeHistoryHandler($repo, $authority);

        $this->expectException(GraduationAuthorityDeniedException::class);
        $handler->handle(new GetOutcomeHistoryQuery(1, 10));
    }
}
