<?php

namespace Tests\Unit\Attendance;

use App\Application\Attendance\Commands\CorrectAttendanceRecordCommand;
use App\Application\Attendance\Commands\CorrectAttendanceRecordHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Domain\Attendance\Data\AttendanceRecordSnapshot;
use App\Domain\Attendance\Data\AttendanceSessionSnapshot;
use App\Domain\Attendance\Events\AttendanceCorrected;
use App\Domain\Attendance\Exceptions\InvalidCorrectionReasonException;
use App\Domain\Attendance\Exceptions\SessionCancelledException;
use App\Domain\Attendance\Repositories\AttendanceWriteRepositoryInterface;
use App\Domain\Attendance\ValueObjects\SessionStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CorrectAttendanceRecordHandlerTest extends TestCase
{
    #[Test]
    public function rejects_empty_reason(): void
    {
        $uow = $this->createMock(UnitOfWork::class);
        $repo = $this->createMock(AttendanceWriteRepositoryInterface::class);
        $outbox = $this->createMock(OutboxRepository::class);
        $idempotency = $this->createMock(IdempotencyStore::class);

        $handler = new CorrectAttendanceRecordHandler($uow, $repo, $outbox, $idempotency);

        $this->expectException(InvalidCorrectionReasonException::class);
        $handler->handle(new CorrectAttendanceRecordCommand(
            10, 50, 2026, 1, 2, null, '   ', 1, 'k1',
        ));
    }

    #[Test]
    public function rejects_cancelled_session(): void
    {
        $uow = $this->createMock(UnitOfWork::class);
        $uow->method('transaction')->willReturnCallback(fn (callable $cb) => $cb());

        $idempotency = $this->createMock(IdempotencyStore::class);
        $idempotency->method('find')->willReturn(null);

        $repo = $this->createMock(AttendanceWriteRepositoryInterface::class);
        $repo->method('lockSessionStatus')->willReturn(new AttendanceSessionSnapshot(
            10, 5, 3, 2026, '2026-10-01', null, 7, SessionStatus::Cancelled->value, 1,
        ));

        $handler = new CorrectAttendanceRecordHandler(
            $uow,
            $repo,
            $this->createMock(OutboxRepository::class),
            $idempotency,
        );

        $this->expectException(SessionCancelledException::class);
        $handler->handle(new CorrectAttendanceRecordCommand(
            10, 50, 2026, 1, 2, 'late arrival', 'typo', 1, 'k1',
        ));
    }

    #[Test]
    public function stages_previous_and_new_in_outbox(): void
    {
        $uow = $this->createMock(UnitOfWork::class);
        $uow->method('transaction')->willReturnCallback(fn (callable $cb) => $cb());

        $idempotency = $this->createMock(IdempotencyStore::class);
        $idempotency->method('find')->willReturn(null);
        $idempotency->expects($this->once())->method('store');

        $repo = $this->createMock(AttendanceWriteRepositoryInterface::class);
        $repo->method('lockSessionStatus')->willReturn(new AttendanceSessionSnapshot(
            10, 5, 3, 2026, '2026-10-01', null, 7, SessionStatus::Closed->value, 1,
        ));
        $repo->method('findRecord')->willReturn(new AttendanceRecordSnapshot(
            99, 10, 50, 100, 2026, 1, '2026-10-01', 1, 'ok', 1,
        ));
        $repo->expects($this->once())->method('updateRecordStatus');
        $repo->expects($this->once())->method('refreshDailySectionSummary');

        $outbox = $this->createMock(OutboxRepository::class);
        $outbox->expects($this->once())->method('stage')->with($this->callback(
            function (AttendanceCorrected $event): bool {
                $p = $event->payload();

                return $p['previous_status'] === 1
                    && $p['new_status'] === 3
                    && $p['reason'] === 'was late'
                    && $p['record_id'] === 99;
            },
        ));

        $handler = new CorrectAttendanceRecordHandler($uow, $repo, $outbox, $idempotency);
        $result = $handler->handle(new CorrectAttendanceRecordCommand(
            10, 50, 2026, 1, 3, 'late', 'was late', 1, 'k-correct',
        ));

        $this->assertSame(99, $result->recordId);
        $this->assertSame(1, $result->previousStatus);
        $this->assertSame(3, $result->newStatus);
    }
}
