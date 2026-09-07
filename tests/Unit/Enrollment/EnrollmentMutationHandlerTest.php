<?php

namespace Tests\Unit\Enrollment;

use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Enrollment\Commands\CancelEnrollmentCommand;
use App\Application\Enrollment\Commands\CancelEnrollmentHandler;
use App\Application\Enrollment\Commands\UpdateEnrollmentPlacementCommand;
use App\Application\Enrollment\Commands\UpdateEnrollmentPlacementHandler;
use App\Domain\Enrollment\Data\EnrollmentSnapshot;
use App\Domain\Enrollment\Events\EnrollmentCancelled;
use App\Domain\Enrollment\Events\EnrollmentPlacementUpdated;
use App\Domain\Enrollment\Exceptions\EnrollmentNotActiveException;
use App\Domain\Enrollment\Exceptions\EnrollmentNotFoundException;
use App\Domain\Enrollment\Repositories\EnrollmentPlacementRepositoryInterface;
use App\Domain\Enrollment\Repositories\EnrollmentRepositoryInterface;
use PHPUnit\Framework\TestCase;

class EnrollmentMutationHandlerTest extends TestCase
{
    public function test_cancel_enrollment_stages_event_and_sets_effective_to(): void
    {
        $snapshot = new EnrollmentSnapshot(
            id: 5,
            studentId: 10,
            schoolId: 1,
            academicYearId: 2026,
            classId: 3,
            sectionId: 4,
            specializationId: null,
            enrollmentNumber: 'ENR-1',
            status: 1,
            effectiveFrom: '2026-09-01',
            effectiveTo: null,
        );

        $enrollments = $this->createMock(EnrollmentRepositoryInterface::class);
        $enrollments->method('findById')->willReturn($snapshot);
        $enrollments->expects($this->once())->method('cancel')->with(5, '2026-09-15');

        $outbox = $this->createMock(OutboxRepository::class);
        $outbox->expects($this->once())->method('stage')->with($this->isInstanceOf(EnrollmentCancelled::class));

        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork->method('transaction')->willReturnCallback(fn (callable $callback) => $callback());

        $handler = new CancelEnrollmentHandler(
            $unitOfWork,
            $enrollments,
            $outbox,
            $this->createMock(IdempotencyStore::class),
        );

        $result = $handler->handle(new CancelEnrollmentCommand(5, 1, '2026-09-15', 99));

        $this->assertSame(5, $result->enrollmentId);
        $this->assertSame('2026-09-15', $result->effectiveTo);
    }

    public function test_cancel_rejects_inactive_enrollment(): void
    {
        $snapshot = new EnrollmentSnapshot(
            id: 5,
            studentId: 10,
            schoolId: 1,
            academicYearId: 2026,
            classId: 3,
            sectionId: 4,
            specializationId: null,
            enrollmentNumber: 'ENR-1',
            status: 2,
            effectiveFrom: '2026-09-01',
            effectiveTo: '2026-09-10',
        );

        $enrollments = $this->createMock(EnrollmentRepositoryInterface::class);
        $enrollments->method('findById')->willReturn($snapshot);

        $handler = new CancelEnrollmentHandler(
            $this->createMock(UnitOfWork::class),
            $enrollments,
            $this->createMock(OutboxRepository::class),
            $this->createMock(IdempotencyStore::class),
        );

        $this->expectException(EnrollmentNotActiveException::class);
        $handler->handle(new CancelEnrollmentCommand(5, 1, '2026-09-15'));
    }

    public function test_update_placement_stages_event(): void
    {
        $snapshot = new EnrollmentSnapshot(
            id: 5,
            studentId: 10,
            schoolId: 1,
            academicYearId: 2026,
            classId: 3,
            sectionId: 4,
            specializationId: null,
            enrollmentNumber: 'ENR-1',
            status: 1,
            effectiveFrom: '2026-09-01',
            effectiveTo: null,
        );

        $enrollments = $this->createMock(EnrollmentRepositoryInterface::class);
        $enrollments->method('findById')->willReturn($snapshot);
        $enrollments->expects($this->once())->method('updatePlacement')->with(5, 8, 9, null);

        $placement = $this->createMock(EnrollmentPlacementRepositoryInterface::class);
        $placement->method('classBelongsToSchool')->willReturn(true);
        $placement->method('sectionBelongsToClass')->willReturn(true);

        $outbox = $this->createMock(OutboxRepository::class);
        $outbox->expects($this->once())->method('stage')->with($this->isInstanceOf(EnrollmentPlacementUpdated::class));

        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork->method('transaction')->willReturnCallback(fn (callable $callback) => $callback());

        $handler = new UpdateEnrollmentPlacementHandler(
            $unitOfWork,
            $enrollments,
            $placement,
            $outbox,
            $this->createMock(IdempotencyStore::class),
        );

        $result = $handler->handle(new UpdateEnrollmentPlacementCommand(5, 1, 8, 9));

        $this->assertSame(8, $result->classId);
        $this->assertSame(9, $result->sectionId);
    }

    public function test_update_rejects_cross_school_enrollment_id(): void
    {
        $snapshot = new EnrollmentSnapshot(
            id: 5,
            studentId: 10,
            schoolId: 2,
            academicYearId: 2026,
            classId: 3,
            sectionId: 4,
            specializationId: null,
            enrollmentNumber: 'ENR-1',
            status: 1,
            effectiveFrom: '2026-09-01',
            effectiveTo: null,
        );

        $enrollments = $this->createMock(EnrollmentRepositoryInterface::class);
        $enrollments->method('findById')->willReturn($snapshot);

        $handler = new UpdateEnrollmentPlacementHandler(
            $this->createMock(UnitOfWork::class),
            $enrollments,
            $this->createMock(EnrollmentPlacementRepositoryInterface::class),
            $this->createMock(OutboxRepository::class),
            $this->createMock(IdempotencyStore::class),
        );

        $this->expectException(EnrollmentNotFoundException::class);
        $handler->handle(new UpdateEnrollmentPlacementCommand(5, 1, 8, 9));
    }
}
