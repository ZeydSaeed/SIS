<?php

namespace Tests\Unit\Graduation;

use App\Application\Graduation\Contracts\GraduationAuthorityPort;
use App\Application\Graduation\Contracts\GraduationReadRepositoryInterface;
use App\Application\Graduation\DTOs\RequirementEvaluationItemDTO;
use App\Application\Graduation\DTOs\RequirementEvaluationsDTO;
use App\Application\Graduation\Queries\GetRequirementEvaluationsHandler;
use App\Application\Graduation\Queries\GetRequirementEvaluationsQuery;
use App\Domain\Graduation\Exceptions\CompletionOutcomeNotFoundException;
use App\Domain\Graduation\Exceptions\GraduationAuthorityDeniedException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GetRequirementEvaluationsHandlerTest extends TestCase
{
    private function dtoWithItems(): RequirementEvaluationsDTO
    {
        return new RequirementEvaluationsDTO(
            schoolId: 1,
            enrollmentId: 10,
            completionOutcomeId: 100,
            completionOutcomeVersionId: 200,
            evaluations: [
                new RequirementEvaluationItemDTO(1, 1, 200, 11, 1, '2026-01-01 00:00:00+00', null),
                new RequirementEvaluationItemDTO(2, 1, 200, 12, 3, '2026-01-01 00:00:00+00', 'note'),
            ],
        );
    }

    #[Test]
    public function returns_requirement_evaluation_facts(): void
    {
        $repo = $this->createMock(GraduationReadRepositoryInterface::class);
        $repo->method('findRequirementEvaluations')->with(1, 10)->willReturn($this->dtoWithItems());

        $authority = $this->createMock(GraduationAuthorityPort::class);
        $authority->expects($this->once())->method('assertSchoolMatches')->with(1);

        $handler = new GetRequirementEvaluationsHandler($repo, $authority);
        $result = $handler->handle(new GetRequirementEvaluationsQuery(1, 10));

        $this->assertSame(100, $result->completionOutcomeId);
        $this->assertSame(200, $result->completionOutcomeVersionId);
        $this->assertCount(2, $result->evaluations);
        $this->assertSame(1, $result->evaluations[0]->resultStatus);
        $this->assertSame(3, $result->evaluations[1]->resultStatus);
    }

    #[Test]
    public function missing_outcome_throws(): void
    {
        $repo = $this->createMock(GraduationReadRepositoryInterface::class);
        $repo->method('findRequirementEvaluations')->willReturn(null);

        $authority = $this->createMock(GraduationAuthorityPort::class);
        $authority->method('assertSchoolMatches');

        $handler = new GetRequirementEvaluationsHandler($repo, $authority);

        $this->expectException(CompletionOutcomeNotFoundException::class);
        $handler->handle(new GetRequirementEvaluationsQuery(1, 10));
    }

    #[Test]
    public function empty_evaluations_are_returned_when_outcome_exists(): void
    {
        $empty = new RequirementEvaluationsDTO(1, 10, 100, 200, []);
        $repo = $this->createMock(GraduationReadRepositoryInterface::class);
        $repo->method('findRequirementEvaluations')->willReturn($empty);

        $authority = $this->createMock(GraduationAuthorityPort::class);
        $authority->method('assertSchoolMatches');

        $handler = new GetRequirementEvaluationsHandler($repo, $authority);
        $result = $handler->handle(new GetRequirementEvaluationsQuery(1, 10));

        $this->assertSame([], $result->evaluations);
        $this->assertSame(100, $result->completionOutcomeId);
    }

    #[Test]
    public function school_mismatch_rejects_before_read(): void
    {
        $repo = $this->createMock(GraduationReadRepositoryInterface::class);
        $repo->expects($this->never())->method('findRequirementEvaluations');

        $authority = $this->createMock(GraduationAuthorityPort::class);
        $authority->method('assertSchoolMatches')
            ->willThrowException(GraduationAuthorityDeniedException::schoolMismatch());

        $handler = new GetRequirementEvaluationsHandler($repo, $authority);

        $this->expectException(GraduationAuthorityDeniedException::class);
        $handler->handle(new GetRequirementEvaluationsQuery(1, 10));
    }

    #[Test]
    public function dto_exposes_raw_result_status_not_invented_labels(): void
    {
        $item = $this->dtoWithItems()->evaluations[0]->toArray();
        $this->assertArrayHasKey('result_status', $item);
        $this->assertArrayNotHasKey('passed', $item);
        $this->assertArrayNotHasKey('eligible', $item);
        $this->assertArrayNotHasKey('status_label', $item);
        $this->assertArrayNotHasKey('student_status', $item);
    }
}
