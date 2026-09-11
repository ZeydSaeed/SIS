<?php

namespace Tests\Unit\Graduation;

use App\Application\Graduation\Contracts\GraduationAuthorityPort;
use App\Application\Graduation\Contracts\GraduationReadRepositoryInterface;
use App\Application\Graduation\DTOs\GraduationAwardDTO;
use App\Application\Graduation\DTOs\GraduationAwardVersionDTO;
use App\Application\Graduation\Queries\GetGraduationAwardHandler;
use App\Application\Graduation\Queries\GetGraduationAwardQuery;
use App\Domain\Graduation\Exceptions\GraduationAuthorityDeniedException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GetGraduationAwardHandlerTest extends TestCase
{
    private function versionDto(
        int $awardVersionId = 50,
        int $lifecycleStatus = 1,
        bool $isCurrentIssued = true,
    ): GraduationAwardVersionDTO {
        return new GraduationAwardVersionDTO(
            awardVersionId: $awardVersionId,
            versionNo: 1,
            graduationApprovalId: 30,
            completionOutcomeVersionId: 20,
            lifecycleStatus: $lifecycleStatus,
            isCurrentIssued: $isCurrentIssued,
            awardedAt: '2026-01-01 00:00:00+00',
            issuedBy: 8,
            awardNumber: null,
            honorsCode: null,
            supersedesVersionId: null,
            supersededByVersionId: null,
            correlationId: 'c-award',
        );
    }

    private function awardDto(?GraduationAwardVersionDTO $version = null, ?int $pointer = 50): GraduationAwardDTO
    {
        return new GraduationAwardDTO(
            schoolId: 1,
            enrollmentId: 10,
            awardId: 100,
            studentId: 5,
            academicYearId: 9,
            specializationId: null,
            currentIssuedVersionId: $pointer,
            createdBy: 8,
            createdAt: '2026-01-01 00:00:00+00',
            version: $version,
        );
    }

    #[Test]
    public function returns_award_and_pointed_version(): void
    {
        $repo = $this->createMock(GraduationReadRepositoryInterface::class);
        $repo->method('findGraduationAward')->with(1, 10)->willReturn($this->awardDto($this->versionDto()));

        $authority = $this->createMock(GraduationAuthorityPort::class);
        $authority->expects($this->once())->method('assertSchoolMatches')->with(1);

        $handler = new GetGraduationAwardHandler($repo, $authority);
        $result = $handler->handle(new GetGraduationAwardQuery(1, 10));

        $this->assertNotNull($result);
        $this->assertSame(100, $result->awardId);
        $this->assertSame(50, $result->currentIssuedVersionId);
        $this->assertNotNull($result->version);
        $this->assertSame(50, $result->version->awardVersionId);
        $this->assertSame(1, $result->version->lifecycleStatus);
        $this->assertTrue($result->version->isCurrentIssued);
    }

    #[Test]
    public function returns_null_when_award_does_not_exist(): void
    {
        $repo = $this->createMock(GraduationReadRepositoryInterface::class);
        $repo->method('findGraduationAward')->willReturn(null);

        $authority = $this->createMock(GraduationAuthorityPort::class);
        $authority->method('assertSchoolMatches');

        $handler = new GetGraduationAwardHandler($repo, $authority);
        $this->assertNull($handler->handle(new GetGraduationAwardQuery(1, 10)));
    }

    #[Test]
    public function returns_award_with_null_version_when_pointer_is_null(): void
    {
        $dto = $this->awardDto(version: null, pointer: null);
        $repo = $this->createMock(GraduationReadRepositoryInterface::class);
        $repo->method('findGraduationAward')->willReturn($dto);

        $authority = $this->createMock(GraduationAuthorityPort::class);
        $authority->method('assertSchoolMatches');

        $handler = new GetGraduationAwardHandler($repo, $authority);
        $result = $handler->handle(new GetGraduationAwardQuery(1, 10));

        $this->assertNotNull($result);
        $this->assertNull($result->currentIssuedVersionId);
        $this->assertNull($result->version);
    }

    #[Test]
    public function returns_pointed_revoked_version_as_raw_facts(): void
    {
        $revoked = $this->versionDto(awardVersionId: 50, lifecycleStatus: 3, isCurrentIssued: false);
        $repo = $this->createMock(GraduationReadRepositoryInterface::class);
        $repo->method('findGraduationAward')->willReturn($this->awardDto($revoked, 50));

        $authority = $this->createMock(GraduationAuthorityPort::class);
        $authority->method('assertSchoolMatches');

        $handler = new GetGraduationAwardHandler($repo, $authority);
        $result = $handler->handle(new GetGraduationAwardQuery(1, 10));

        $this->assertNotNull($result?->version);
        $this->assertSame(50, $result->version->awardVersionId);
        $this->assertSame(3, $result->version->lifecycleStatus);
        $this->assertFalse($result->version->isCurrentIssued);
    }

    #[Test]
    public function does_not_invent_fallback_when_pointer_null_in_dto_contract(): void
    {
        $dto = $this->awardDto(version: null, pointer: null);
        $this->assertNull($dto->version);
        $this->assertNull($dto->currentIssuedVersionId);
    }

    #[Test]
    public function does_not_replace_revoked_pointed_version_in_dto_contract(): void
    {
        $revoked = $this->versionDto(50, 3, false);
        $dto = $this->awardDto($revoked, 50);
        $this->assertSame(50, $dto->version?->awardVersionId);
        $this->assertSame(3, $dto->version?->lifecycleStatus);
    }

    #[Test]
    public function maps_raw_lifecycle_status_and_is_current_issued(): void
    {
        $item = $this->versionDto(lifecycleStatus: 3, isCurrentIssued: false)->toArray();
        $this->assertSame(3, $item['lifecycle_status']);
        $this->assertFalse($item['is_current_issued']);
    }

    #[Test]
    public function does_not_expose_synthetic_student_status_or_sod_fields(): void
    {
        $award = $this->awardDto($this->versionDto())->toArray();
        $version = $award['version'];

        $this->assertArrayNotHasKey('student_status', $award);
        $this->assertArrayNotHasKey('is_graduated', $award);
        $this->assertArrayNotHasKey('graduated', $award);
        $this->assertArrayNotHasKey('award_status_label', $award);
        $this->assertArrayNotHasKey('graduation_status', $award);
        $this->assertArrayNotHasKey('sod_valid', $award);
        $this->assertArrayNotHasKey('is_currently_graduated', $award);

        $this->assertIsArray($version);
        $this->assertArrayHasKey('lifecycle_status', $version);
        $this->assertArrayHasKey('is_current_issued', $version);
        $this->assertArrayNotHasKey('status_label', $version);
        $this->assertArrayNotHasKey('issued', $version);
        $this->assertArrayNotHasKey('revoked', $version);
    }

    #[Test]
    public function school_mismatch_rejects_before_repository_access(): void
    {
        $repo = $this->createMock(GraduationReadRepositoryInterface::class);
        $repo->expects($this->never())->method('findGraduationAward');

        $authority = $this->createMock(GraduationAuthorityPort::class);
        $authority->method('assertSchoolMatches')
            ->willThrowException(GraduationAuthorityDeniedException::schoolMismatch());

        $handler = new GetGraduationAwardHandler($repo, $authority);

        $this->expectException(GraduationAuthorityDeniedException::class);
        $handler->handle(new GetGraduationAwardQuery(1, 10));
    }
}
