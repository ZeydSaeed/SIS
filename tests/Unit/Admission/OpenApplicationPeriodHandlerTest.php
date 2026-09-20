<?php

namespace Tests\Unit\Admission;

use App\Application\Admission\Commands\OpenApplicationPeriodCommand;
use App\Application\Admission\Commands\OpenApplicationPeriodHandler;
use App\Application\Admission\Support\ApplicationPeriodAcademicYearGuard;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Domain\Academic\Data\AcademicYearSnapshot;
use App\Domain\Academic\Repositories\AcademicYearRepositoryInterface;
use App\Domain\Admission\Data\CreateApplicationPeriodData;
use App\Domain\Admission\Events\ApplicationPeriodOpened;
use App\Domain\Admission\Repositories\AdmissionRepositoryInterface;
use DomainException;
use PHPUnit\Framework\TestCase;

class OpenApplicationPeriodHandlerTest extends TestCase
{
    public function test_opens_period_inside_academic_year(): void
    {
        $admission = $this->createMock(AdmissionRepositoryInterface::class);
        $admission->expects($this->once())
            ->method('createPeriod')
            ->with($this->callback(fn (CreateApplicationPeriodData $data): bool => $data->academicYearId === 9
                && $data->name === 'فترة جديدة'
                && $data->startDate === '2026-09-01T08:00'
                && $data->endDate === '2026-09-30T16:00'))
            ->willReturn(11);

        $outbox = $this->createMock(OutboxRepository::class);
        $outbox->expects($this->once())->method('stage')->with($this->isInstanceOf(ApplicationPeriodOpened::class));

        $result = $this->handler($admission, $outbox)->handle(new OpenApplicationPeriodCommand(
            schoolId: 1,
            academicYearId: 9,
            name: 'فترة جديدة',
            startDate: '2026-09-01T08:00',
            endDate: '2026-09-30T16:00',
            maxApplications: 20,
        ));

        $this->assertTrue($result->success);
        $this->assertSame(11, $result->periodId);
    }

    public function test_rejects_dates_outside_academic_year(): void
    {
        $admission = $this->createMock(AdmissionRepositoryInterface::class);
        $admission->expects($this->never())->method('createPeriod');

        $this->expectException(DomainException::class);
        $this->handler($admission)->handle(new OpenApplicationPeriodCommand(
            schoolId: 1,
            academicYearId: 9,
            name: 'فترة',
            startDate: '2025-09-01T08:00',
            endDate: '2025-09-30T16:00',
        ));
    }

    private function handler(
        AdmissionRepositoryInterface $admission,
        ?OutboxRepository $outbox = null,
    ): OpenApplicationPeriodHandler {
        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork->method('transaction')->willReturnCallback(fn (callable $callback) => $callback());

        $years = $this->createMock(AcademicYearRepositoryInterface::class);
        $years->method('findById')->willReturn(new AcademicYearSnapshot(
            id: 9,
            code: '2026-2027',
            name: 'السنة الدراسية 2026-2027',
            startDate: '2026-09-01',
            endDate: '2027-06-30',
            isCurrent: true,
            status: 1,
            createdAt: '2026-01-01 00:00:00',
            updatedAt: '2026-01-01 00:00:00',
        ));

        return new OpenApplicationPeriodHandler(
            $unitOfWork,
            $admission,
            $outbox ?? $this->createMock(OutboxRepository::class),
            $this->createMock(IdempotencyStore::class),
            new ApplicationPeriodAcademicYearGuard($years),
        );
    }
}
