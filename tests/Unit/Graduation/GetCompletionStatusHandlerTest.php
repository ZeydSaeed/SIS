<?php

namespace Tests\Unit\Graduation;

use App\Application\Graduation\Contracts\GraduationAuthorityPort;
use App\Application\Graduation\Contracts\GraduationReadRepositoryInterface;
use App\Application\Graduation\DTOs\CompletionStatusDTO;
use App\Application\Graduation\Queries\GetCompletionStatusHandler;
use App\Application\Graduation\Queries\GetCompletionStatusQuery;
use App\Domain\Graduation\Exceptions\CompletionOutcomeNotFoundException;
use App\Domain\Graduation\Exceptions\GraduationAuthorityDeniedException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GetCompletionStatusHandlerTest extends TestCase
{
    private function dto(): CompletionStatusDTO
    {
        return new CompletionStatusDTO(
            schoolId: 1,
            enrollmentId: 10,
            completionOutcomeId: 100,
            studentId: 50,
            academicYearId: 2025,
            currentOfficialVersionId: null,
            versionId: 200,
            versionNo: 1,
            lifecycleStatus: 1,
            evaluationStatus: 2,
            eligibilityStatus: 2,
            isCurrentOfficial: false,
            eligibilityPolicyVersionId: 7,
            calculationVersion: 'calc-1',
            evaluatedAt: '2026-01-01 00:00:00+00',
        );
    }

    #[Test]
    public function returns_mapped_completion_status(): void
    {
        $repo = $this->createMock(GraduationReadRepositoryInterface::class);
        $repo->method('findCompletionStatus')->with(1, 10)->willReturn($this->dto());

        $authority = $this->createMock(GraduationAuthorityPort::class);
        $authority->expects($this->once())->method('assertSchoolMatches')->with(1);

        $handler = new GetCompletionStatusHandler($repo, $authority);
        $result = $handler->handle(new GetCompletionStatusQuery(1, 10));

        $this->assertSame(100, $result->completionOutcomeId);
        $this->assertSame(2, $result->eligibilityStatus);
        $this->assertSame(50, $result->studentId);
    }

    #[Test]
    public function not_found_throws_domain_exception(): void
    {
        $repo = $this->createMock(GraduationReadRepositoryInterface::class);
        $repo->method('findCompletionStatus')->willReturn(null);

        $authority = $this->createMock(GraduationAuthorityPort::class);
        $authority->method('assertSchoolMatches');

        $handler = new GetCompletionStatusHandler($repo, $authority);

        $this->expectException(CompletionOutcomeNotFoundException::class);
        $handler->handle(new GetCompletionStatusQuery(1, 10));
    }

    #[Test]
    public function school_mismatch_is_rejected_before_read(): void
    {
        $repo = $this->createMock(GraduationReadRepositoryInterface::class);
        $repo->expects($this->never())->method('findCompletionStatus');

        $authority = $this->createMock(GraduationAuthorityPort::class);
        $authority->method('assertSchoolMatches')
            ->willThrowException(GraduationAuthorityDeniedException::schoolMismatch());

        $handler = new GetCompletionStatusHandler($repo, $authority);

        $this->expectException(GraduationAuthorityDeniedException::class);
        $handler->handle(new GetCompletionStatusQuery(1, 10));
    }

    #[Test]
    public function dto_does_not_include_student_status_field(): void
    {
        $keys = array_keys($this->dto()->toArray());
        $this->assertNotContains('student_status', $keys);
        $this->assertNotContains('status_label', $keys);
        $this->assertNotContains('graduated', $keys);
    }
}
