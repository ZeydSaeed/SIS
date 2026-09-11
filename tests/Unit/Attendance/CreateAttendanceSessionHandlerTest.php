<?php

namespace Tests\Unit\Attendance;

use App\Application\Attendance\Commands\CreateAttendanceSessionCommand;
use App\Application\Attendance\Commands\CreateAttendanceSessionHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Domain\Attendance\Exceptions\DuplicateOpenAttendanceSessionException;
use App\Domain\Attendance\Repositories\AttendanceWriteRepositoryInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CreateAttendanceSessionHandlerTest extends TestCase
{
    #[Test]
    public function duplicate_open_session_raises_conflict_before_insert(): void
    {
        $uow = $this->createMock(UnitOfWork::class);
        $uow->expects($this->never())->method('transaction');

        $idempotency = $this->createMock(IdempotencyStore::class);
        $idempotency->method('find')->willReturn(null);

        $repo = $this->createMock(AttendanceWriteRepositoryInterface::class);
        $repo->method('resolveSchoolIdForSection')->willReturn(1);
        $repo->method('academicYearContainsDate')->willReturn(true);
        $repo->method('findDuplicateOpenSession')->willReturn(99);

        $handler = new CreateAttendanceSessionHandler(
            $uow,
            $repo,
            $this->createMock(OutboxRepository::class),
            $idempotency,
        );

        $this->expectException(DuplicateOpenAttendanceSessionException::class);
        $handler->handle(new CreateAttendanceSessionCommand(
            schoolId: 1,
            academicYearId: 2026,
            sectionId: 5,
            subjectId: 3,
            sessionDate: '2026-10-01',
            teacherId: 7,
            periodId: null,
        ));
    }
}
