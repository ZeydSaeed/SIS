<?php

namespace Tests\Unit\Attendance;

use App\Application\Attendance\Commands\CloseAttendanceSessionCommand;
use App\Application\Attendance\Commands\CloseAttendanceSessionHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Domain\Attendance\Data\AttendanceSessionSnapshot;
use App\Domain\Attendance\Exceptions\SessionCloseConflictException;
use App\Domain\Attendance\Repositories\AttendanceWriteRepositoryInterface;
use App\Domain\Attendance\ValueObjects\SessionStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CloseAttendanceSessionHandlerTest extends TestCase
{
    #[Test]
    public function close_conflict_when_already_closed(): void
    {
        $uow = $this->createMock(UnitOfWork::class);
        $uow->method('transaction')->willReturnCallback(fn (callable $cb) => $cb());

        $idempotency = $this->createMock(IdempotencyStore::class);
        $idempotency->method('find')->willReturn(null);

        $repo = $this->createMock(AttendanceWriteRepositoryInterface::class);
        $repo->method('findSessionById')->willReturn(new AttendanceSessionSnapshot(
            10, 5, 3, 2026, '2026-10-01', null, 7, SessionStatus::Closed->value, 1,
        ));
        $repo->method('closeSessionIfOpen')->willReturn(false);

        $handler = new CloseAttendanceSessionHandler(
            $uow,
            $repo,
            $this->createMock(OutboxRepository::class),
            $idempotency,
        );

        $this->expectException(SessionCloseConflictException::class);
        $handler->handle(new CloseAttendanceSessionCommand(10, 1, 1, 'close-1'));
    }

    #[Test]
    public function idempotent_replay_returns_cached_success(): void
    {
        $uow = $this->createMock(UnitOfWork::class);
        $uow->expects($this->never())->method('transaction');

        $idempotency = $this->createMock(IdempotencyStore::class);
        $idempotency->method('find')->willReturn(['session_id' => 10, 'school_id' => 1]);

        $handler = new CloseAttendanceSessionHandler(
            $uow,
            $this->createMock(AttendanceWriteRepositoryInterface::class),
            $this->createMock(OutboxRepository::class),
            $idempotency,
        );

        $result = $handler->handle(new CloseAttendanceSessionCommand(10, 1, 1, 'close-1'));
        $this->assertTrue($result->fromIdempotencyCache);
        $this->assertSame(10, $result->sessionId);
    }
}
