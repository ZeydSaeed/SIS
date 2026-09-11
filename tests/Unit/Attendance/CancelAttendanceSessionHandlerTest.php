<?php

namespace Tests\Unit\Attendance;

use App\Application\Attendance\Commands\CancelAttendanceSessionCommand;
use App\Application\Attendance\Commands\CancelAttendanceSessionHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Domain\Attendance\Data\AttendanceSessionSnapshot;
use App\Domain\Attendance\Events\AttendanceSessionCancelled;
use App\Domain\Attendance\Exceptions\IdempotencyPayloadConflictException;
use App\Domain\Attendance\Exceptions\InvalidCancellationReasonException;
use App\Domain\Attendance\Exceptions\SessionCancelConflictException;
use App\Domain\Attendance\Repositories\AttendanceWriteRepositoryInterface;
use App\Domain\Attendance\ValueObjects\SessionStatus;
use App\Domain\Shared\Exceptions\SisDomainException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CancelAttendanceSessionHandlerTest extends TestCase
{
    #[Test]
    public function open_to_cancelled_stages_event(): void
    {
        $uow = $this->createMock(UnitOfWork::class);
        $uow->method('transaction')->willReturnCallback(fn (callable $cb) => $cb());

        $idempotency = $this->createMock(IdempotencyStore::class);
        $idempotency->method('find')->willReturn(null);
        $idempotency->expects($this->once())->method('store');

        $repo = $this->createMock(AttendanceWriteRepositoryInterface::class);
        $repo->method('findSessionById')->willReturn(new AttendanceSessionSnapshot(
            10, 5, 3, 2026, '2026-10-01', null, 7, SessionStatus::Open->value, 1,
        ));
        $repo->method('cancelSessionIfOpenOrClosed')->willReturn(SessionStatus::Open->value);

        $outbox = $this->createMock(OutboxRepository::class);
        $outbox->expects($this->once())->method('stage')->with($this->isInstanceOf(AttendanceSessionCancelled::class));

        $handler = new CancelAttendanceSessionHandler($uow, $repo, $outbox, $idempotency);
        $result = $handler->handle(new CancelAttendanceSessionCommand(
            sessionId: 10,
            schoolId: 1,
            reason: 'Wrong section',
            cancelledBy: 99,
            idempotencyKey: 'cancel-1',
        ));

        $this->assertSame(10, $result->sessionId);
        $this->assertSame(SessionStatus::Open->value, $result->previousStatus);
        $this->assertSame(SessionStatus::Cancelled->value, $result->newStatus);
        $this->assertFalse($result->fromIdempotencyCache);
    }

    #[Test]
    public function closed_to_cancelled_succeeds(): void
    {
        $uow = $this->createMock(UnitOfWork::class);
        $uow->method('transaction')->willReturnCallback(fn (callable $cb) => $cb());

        $idempotency = $this->createMock(IdempotencyStore::class);
        $idempotency->method('find')->willReturn(null);

        $repo = $this->createMock(AttendanceWriteRepositoryInterface::class);
        $repo->method('findSessionById')->willReturn(new AttendanceSessionSnapshot(
            10, 5, 3, 2026, '2026-10-01', null, 7, SessionStatus::Closed->value, 1,
        ));
        $repo->method('cancelSessionIfOpenOrClosed')->willReturn(SessionStatus::Closed->value);

        $handler = new CancelAttendanceSessionHandler(
            $uow,
            $repo,
            $this->createMock(OutboxRepository::class),
            $idempotency,
        );

        $result = $handler->handle(new CancelAttendanceSessionCommand(
            10, 1, 'Void closed session', 1, 'cancel-closed',
        ));

        $this->assertSame(SessionStatus::Closed->value, $result->previousStatus);
        $this->assertSame(SessionStatus::Cancelled->value, $result->newStatus);
    }

    #[Test]
    public function already_cancelled_conflicts(): void
    {
        $uow = $this->createMock(UnitOfWork::class);
        $uow->method('transaction')->willReturnCallback(fn (callable $cb) => $cb());

        $idempotency = $this->createMock(IdempotencyStore::class);
        $idempotency->method('find')->willReturn(null);

        $repo = $this->createMock(AttendanceWriteRepositoryInterface::class);
        $repo->method('findSessionById')->willReturn(new AttendanceSessionSnapshot(
            10, 5, 3, 2026, '2026-10-01', null, 7, SessionStatus::Cancelled->value, 1,
        ));
        $repo->method('cancelSessionIfOpenOrClosed')->willReturn(null);

        $handler = new CancelAttendanceSessionHandler(
            $uow,
            $repo,
            $this->createMock(OutboxRepository::class),
            $idempotency,
        );

        $this->expectException(SessionCancelConflictException::class);
        $handler->handle(new CancelAttendanceSessionCommand(10, 1, 'Again', 1, 'cancel-dup'));
    }

    #[Test]
    public function empty_idempotency_key_rejected(): void
    {
        $handler = new CancelAttendanceSessionHandler(
            $this->createMock(UnitOfWork::class),
            $this->createMock(AttendanceWriteRepositoryInterface::class),
            $this->createMock(OutboxRepository::class),
            $this->createMock(IdempotencyStore::class),
        );

        $this->expectException(SisDomainException::class);
        $handler->handle(new CancelAttendanceSessionCommand(10, 1, 'Valid reason', 1, '  '));
    }

    #[Test]
    public function empty_reason_rejected(): void
    {
        $handler = new CancelAttendanceSessionHandler(
            $this->createMock(UnitOfWork::class),
            $this->createMock(AttendanceWriteRepositoryInterface::class),
            $this->createMock(OutboxRepository::class),
            $this->createMock(IdempotencyStore::class),
        );

        $this->expectException(InvalidCancellationReasonException::class);
        $handler->handle(new CancelAttendanceSessionCommand(10, 1, '   ', 1, 'cancel-empty'));
    }

    #[Test]
    public function idempotent_replay_returns_cache(): void
    {
        $uow = $this->createMock(UnitOfWork::class);
        $uow->expects($this->never())->method('transaction');

        $idempotency = $this->createMock(IdempotencyStore::class);
        $idempotency->method('find')->willReturn([
            'session_id' => 10,
            'school_id' => 1,
            'reason' => 'Wrong section',
            'previous_status' => 1,
            'new_status' => 3,
        ]);

        $handler = new CancelAttendanceSessionHandler(
            $uow,
            $this->createMock(AttendanceWriteRepositoryInterface::class),
            $this->createMock(OutboxRepository::class),
            $idempotency,
        );

        $result = $handler->handle(new CancelAttendanceSessionCommand(
            10, 1, 'Wrong section', 1, 'cancel-replay',
        ));

        $this->assertTrue($result->fromIdempotencyCache);
        $this->assertSame(3, $result->newStatus);
    }

    #[Test]
    public function same_key_different_payload_rejected(): void
    {
        $idempotency = $this->createMock(IdempotencyStore::class);
        $idempotency->method('find')->willReturn([
            'session_id' => 10,
            'school_id' => 1,
            'reason' => 'Original reason',
            'previous_status' => 1,
            'new_status' => 3,
        ]);

        $handler = new CancelAttendanceSessionHandler(
            $this->createMock(UnitOfWork::class),
            $this->createMock(AttendanceWriteRepositoryInterface::class),
            $this->createMock(OutboxRepository::class),
            $idempotency,
        );

        $this->expectException(IdempotencyPayloadConflictException::class);
        $handler->handle(new CancelAttendanceSessionCommand(
            10, 1, 'Different reason', 1, 'cancel-conflict-key',
        ));
    }
}
