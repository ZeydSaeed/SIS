<?php

namespace Tests\Unit\Graduation;

use App\Application\Graduation\Contracts\GraduationAuthorityPort;
use App\Application\Graduation\Contracts\GraduationReadRepositoryInterface;
use App\Application\Graduation\DTOs\GraduationApprovalItemDTO;
use App\Application\Graduation\DTOs\GraduationApprovalsDTO;
use App\Application\Graduation\Queries\GetGraduationApprovalHandler;
use App\Application\Graduation\Queries\GetGraduationApprovalQuery;
use App\Domain\Graduation\Exceptions\CompletionOutcomeNotFoundException;
use App\Domain\Graduation\Exceptions\GraduationAuthorityDeniedException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GetGraduationApprovalHandlerTest extends TestCase
{
    private function dto(): GraduationApprovalsDTO
    {
        return new GraduationApprovalsDTO(
            schoolId: 1,
            enrollmentId: 10,
            completionOutcomeId: 100,
            completionOutcomeVersionId: 200,
            approvals: [
                new GraduationApprovalItemDTO(
                    id: 1,
                    schoolId: 1,
                    enrollmentId: 10,
                    completionOutcomeVersionId: 200,
                    attemptNo: 1,
                    decisionStatus: 2,
                    requestedAt: '2026-01-01 00:00:00+00',
                    requestedBy: 8,
                    decidedAt: '2026-01-01 00:00:00+00',
                    decidedBy: 8,
                    decisionReasonRef: null,
                    correlationId: 'c1',
                    createdAt: '2026-01-01 00:00:00+00',
                ),
            ],
        );
    }

    #[Test]
    public function returns_approval_facts(): void
    {
        $repo = $this->createMock(GraduationReadRepositoryInterface::class);
        $repo->method('findGraduationApprovals')->with(1, 10)->willReturn($this->dto());

        $authority = $this->createMock(GraduationAuthorityPort::class);
        $authority->expects($this->once())->method('assertSchoolMatches')->with(1);

        $handler = new GetGraduationApprovalHandler($repo, $authority);
        $result = $handler->handle(new GetGraduationApprovalQuery(1, 10));

        $this->assertSame(100, $result->completionOutcomeId);
        $this->assertCount(1, $result->approvals);
        $this->assertSame(2, $result->approvals[0]->decisionStatus);
        $this->assertSame(8, $result->approvals[0]->decidedBy);
    }

    #[Test]
    public function missing_outcome_throws(): void
    {
        $repo = $this->createMock(GraduationReadRepositoryInterface::class);
        $repo->method('findGraduationApprovals')->willReturn(null);

        $authority = $this->createMock(GraduationAuthorityPort::class);
        $authority->method('assertSchoolMatches');

        $handler = new GetGraduationApprovalHandler($repo, $authority);

        $this->expectException(CompletionOutcomeNotFoundException::class);
        $handler->handle(new GetGraduationApprovalQuery(1, 10));
    }

    #[Test]
    public function empty_approvals_when_outcome_exists(): void
    {
        $empty = new GraduationApprovalsDTO(1, 10, 100, 200, []);
        $repo = $this->createMock(GraduationReadRepositoryInterface::class);
        $repo->method('findGraduationApprovals')->willReturn($empty);

        $authority = $this->createMock(GraduationAuthorityPort::class);
        $authority->method('assertSchoolMatches');

        $handler = new GetGraduationApprovalHandler($repo, $authority);
        $result = $handler->handle(new GetGraduationApprovalQuery(1, 10));

        $this->assertSame([], $result->approvals);
    }

    #[Test]
    public function school_mismatch_rejects_before_read(): void
    {
        $repo = $this->createMock(GraduationReadRepositoryInterface::class);
        $repo->expects($this->never())->method('findGraduationApprovals');

        $authority = $this->createMock(GraduationAuthorityPort::class);
        $authority->method('assertSchoolMatches')
            ->willThrowException(GraduationAuthorityDeniedException::schoolMismatch());

        $handler = new GetGraduationApprovalHandler($repo, $authority);

        $this->expectException(GraduationAuthorityDeniedException::class);
        $handler->handle(new GetGraduationApprovalQuery(1, 10));
    }

    #[Test]
    public function dto_exposes_raw_decision_status_not_invented_labels_or_sod_checks(): void
    {
        $item = $this->dto()->approvals[0]->toArray();
        $this->assertArrayHasKey('decision_status', $item);
        $this->assertArrayHasKey('decided_by', $item);
        $this->assertArrayNotHasKey('approved', $item);
        $this->assertArrayNotHasKey('graduated', $item);
        $this->assertArrayNotHasKey('status_label', $item);
        $this->assertArrayNotHasKey('sod_valid', $item);
        $this->assertArrayNotHasKey('student_status', $item);
    }
}
