<?php

namespace Tests\Unit\Attendance;

use App\Application\Attendance\Commands\MarkSectionAttendanceCommand;
use App\Application\Attendance\Commands\MarkSectionAttendanceHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Domain\Attendance\Data\AttendanceSessionSnapshot;
use App\Domain\Attendance\Data\EnrollmentAttendanceContext;
use App\Domain\Attendance\Exceptions\DuplicateStudentInPayloadException;
use App\Domain\Attendance\Exceptions\EmptyAttendancePayloadException;
use App\Domain\Attendance\Exceptions\InvalidEnrollmentForAttendanceException;
use App\Domain\Attendance\Exceptions\SessionNotOpenException;
use App\Domain\Attendance\Repositories\AttendanceWriteRepositoryInterface;
use App\Domain\Attendance\ValueObjects\SessionStatus;
use App\Domain\Shared\DomainEvent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MarkSectionAttendanceHandlerTest extends TestCase
{
    private function session(int $status = 1): AttendanceSessionSnapshot
    {
        return new AttendanceSessionSnapshot(
            id: 10,
            sectionId: 5,
            subjectId: 3,
            academicYearId: 2026,
            sessionDate: '2026-10-01',
            periodId: null,
            teacherId: 7,
            status: $status,
            resolvedSchoolId: 1,
        );
    }

    private function enrollment(array $overrides = []): EnrollmentAttendanceContext
    {
        return new EnrollmentAttendanceContext(
            id: $overrides['id'] ?? 100,
            studentId: $overrides['studentId'] ?? 50,
            schoolId: $overrides['schoolId'] ?? 1,
            academicYearId: $overrides['academicYearId'] ?? 2026,
            sectionId: $overrides['sectionId'] ?? 5,
            effectiveFrom: $overrides['effectiveFrom'] ?? '2026-09-01',
            effectiveTo: $overrides['effectiveTo'] ?? null,
            status: $overrides['status'] ?? 1,
        );
    }

    /**
     * @return array{0: UnitOfWork, 1: IdempotencyStore, 2: OutboxRepository}
     */
    private function baseDeps(): array
    {
        $uow = $this->createMock(UnitOfWork::class);
        $uow->method('transaction')->willReturnCallback(fn (callable $cb) => $cb());

        $idempotency = $this->createMock(IdempotencyStore::class);
        $idempotency->method('find')->willReturn(null);

        $outbox = $this->createMock(OutboxRepository::class);

        return [$uow, $idempotency, $outbox];
    }

    #[Test]
    public function rejects_empty_payload(): void
    {
        [$uow, $idempotency, $outbox] = $this->baseDeps();
        $repo = $this->createMock(AttendanceWriteRepositoryInterface::class);

        $handler = new MarkSectionAttendanceHandler($uow, $repo, $outbox, $idempotency);

        $this->expectException(EmptyAttendancePayloadException::class);
        $handler->handle(new MarkSectionAttendanceCommand(10, 1, 2026, [], 1, 'k1'));
    }

    #[Test]
    public function rejects_duplicate_student_ids(): void
    {
        [$uow, $idempotency, $outbox] = $this->baseDeps();
        $repo = $this->createMock(AttendanceWriteRepositoryInterface::class);

        $handler = new MarkSectionAttendanceHandler($uow, $repo, $outbox, $idempotency);

        $this->expectException(DuplicateStudentInPayloadException::class);
        $handler->handle(new MarkSectionAttendanceCommand(10, 1, 2026, [
            ['studentId' => 50, 'enrollmentId' => 100, 'status' => 1],
            ['studentId' => 50, 'enrollmentId' => 101, 'status' => 2],
        ], 1, 'k1'));
    }

    #[Test]
    public function rejects_mark_when_session_closed(): void
    {
        [$uow, $idempotency, $outbox] = $this->baseDeps();
        $repo = $this->createMock(AttendanceWriteRepositoryInterface::class);
        $repo->method('findSessionById')->willReturn($this->session(SessionStatus::Closed->value));
        $repo->method('lockSessionStatus')->willReturn($this->session(SessionStatus::Closed->value));

        $handler = new MarkSectionAttendanceHandler($uow, $repo, $outbox, $idempotency);

        $this->expectException(SessionNotOpenException::class);
        $handler->handle(new MarkSectionAttendanceCommand(10, 1, 2026, [
            ['studentId' => 50, 'enrollmentId' => 100, 'status' => 1],
        ], 1, 'k1'));
    }

    #[Test]
    public function rejects_out_of_window_enrollment_att_d4(): void
    {
        [$uow, $idempotency, $outbox] = $this->baseDeps();
        $repo = $this->createMock(AttendanceWriteRepositoryInterface::class);
        $repo->method('findSessionById')->willReturn($this->session());
        $repo->method('lockSessionStatus')->willReturn($this->session());
        $repo->method('loadEnrollmentForMark')->willReturn($this->enrollment([
            'effectiveFrom' => '2026-09-01',
            'effectiveTo' => '2026-09-15',
        ]));

        $handler = new MarkSectionAttendanceHandler($uow, $repo, $outbox, $idempotency);

        $this->expectException(InvalidEnrollmentForAttendanceException::class);
        $handler->handle(new MarkSectionAttendanceCommand(10, 1, 2026, [
            ['studentId' => 50, 'enrollmentId' => 100, 'status' => 1],
        ], 1, 'k1'));
    }

    #[Test]
    public function partial_mark_upserts_and_stages_outbox(): void
    {
        [$uow, $idempotency, $outbox] = $this->baseDeps();
        $repo = $this->createMock(AttendanceWriteRepositoryInterface::class);
        $repo->method('findSessionById')->willReturn($this->session());
        $repo->method('lockSessionStatus')->willReturn($this->session());
        $repo->method('loadEnrollmentForMark')->willReturn($this->enrollment());
        $repo->expects($this->once())->method('upsertAttendanceRecords')->willReturn(1);
        $repo->expects($this->once())->method('refreshDailySectionSummary');
        $outbox->expects($this->once())->method('stage')->with($this->isInstanceOf(DomainEvent::class));
        $idempotency->expects($this->once())->method('store');

        $handler = new MarkSectionAttendanceHandler($uow, $repo, $outbox, $idempotency);
        $result = $handler->handle(new MarkSectionAttendanceCommand(10, 1, 2026, [
            ['studentId' => 50, 'enrollmentId' => 100, 'status' => 1],
        ], 1, 'k1'));

        $this->assertTrue($result->success);
        $this->assertSame(1, $result->markedCount);
        $this->assertFalse($result->fromIdempotencyCache);
    }

    #[Test]
    public function allows_cancelled_enrollment_still_in_effective_window(): void
    {
        [$uow, $idempotency, $outbox] = $this->baseDeps();
        $repo = $this->createMock(AttendanceWriteRepositoryInterface::class);
        $repo->method('findSessionById')->willReturn($this->session());
        $repo->method('lockSessionStatus')->willReturn($this->session());
        $repo->method('loadEnrollmentForMark')->willReturn($this->enrollment([
            'status' => 2,
            'effectiveFrom' => '2026-09-01',
            'effectiveTo' => '2026-10-15',
        ]));
        $repo->expects($this->once())->method('upsertAttendanceRecords')->willReturn(1);
        $repo->method('refreshDailySectionSummary');
        $outbox->method('stage');
        $idempotency->method('store');

        $handler = new MarkSectionAttendanceHandler($uow, $repo, $outbox, $idempotency);
        $result = $handler->handle(new MarkSectionAttendanceCommand(10, 1, 2026, [
            ['studentId' => 50, 'enrollmentId' => 100, 'status' => 2],
        ], 1, 'k-cancel-ok'));

        $this->assertTrue($result->success);
    }
}
