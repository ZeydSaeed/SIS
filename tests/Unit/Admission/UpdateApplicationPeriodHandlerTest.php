<?php

namespace Tests\Unit\Admission;

use App\Application\Admission\Commands\UpdateApplicationPeriodCommand;
use App\Application\Admission\Commands\UpdateApplicationPeriodHandler;
use App\Application\Admission\Support\ApplicationPeriodAcademicYearGuard;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Domain\Academic\Data\AcademicYearSnapshot;
use App\Domain\Academic\Repositories\AcademicYearRepositoryInterface;
use App\Domain\Admission\Data\UpdateApplicationPeriodData;
use App\Domain\Admission\Events\ApplicationPeriodUpdated;
use App\Domain\Admission\Exceptions\ApplicationPeriodNotFoundException;
use App\Domain\Admission\Repositories\AdmissionRepositoryInterface;
use DomainException;
use PHPUnit\Framework\TestCase;

class UpdateApplicationPeriodHandlerTest extends TestCase
{
    public function test_updates_period_and_stages_outbox_event(): void
    {
        $admission = $this->createMock(AdmissionRepositoryInterface::class);
        $admission->method('findPeriodForSchool')->willReturn($this->periodRow());
        $admission->expects($this->once())
            ->method('updatePeriod')
            ->with($this->callback(fn (UpdateApplicationPeriodData $data): bool => $data->periodId === 4
                && $data->academicYearId === 9
                && $data->name === 'فترة محدثة'
                && $data->startDate === '2026-09-01T08:00'
                && $data->endDate === '2026-09-30T16:00'
                && $data->maxApplications === 20));

        $outbox = $this->createMock(OutboxRepository::class);
        $outbox->expects($this->once())->method('stage')->with($this->isInstanceOf(ApplicationPeriodUpdated::class));

        $handler = $this->handler($admission, $outbox);
        $result = $handler->handle(new UpdateApplicationPeriodCommand(
            schoolId: 1,
            academicYearId: 9,
            periodId: 4,
            name: 'فترة محدثة',
            startDate: '2026-09-01T08:00',
            endDate: '2026-09-30T16:00',
            maxApplications: 20,
        ));

        $this->assertTrue($result->success);
        $this->assertSame(4, $result->periodId);
    }

    public function test_rejects_unknown_period(): void
    {
        $admission = $this->createMock(AdmissionRepositoryInterface::class);
        $admission->method('findPeriodForSchool')->willReturn(null);

        $this->expectException(ApplicationPeriodNotFoundException::class);
        $this->handler($admission)->handle(new UpdateApplicationPeriodCommand(
            schoolId: 1,
            academicYearId: 9,
            periodId: 99,
            name: 'فترة',
            startDate: '2026-09-01T08:00',
            endDate: '2026-09-30T16:00',
        ));
    }

    public function test_rejects_end_before_start(): void
    {
        $this->expectException(DomainException::class);
        $this->handler($this->createMock(AdmissionRepositoryInterface::class))->handle(
            new UpdateApplicationPeriodCommand(
                schoolId: 1,
                academicYearId: 9,
                periodId: 4,
                name: 'فترة',
                startDate: '2026-09-30T08:00',
                endDate: '2026-09-01T16:00',
            ),
        );
    }

    public function test_rejects_dates_outside_academic_year(): void
    {
        $admission = $this->createMock(AdmissionRepositoryInterface::class);
        $admission->method('findPeriodForSchool')->willReturn($this->periodRow());

        $this->expectException(DomainException::class);
        $this->handler($admission)->handle(new UpdateApplicationPeriodCommand(
            schoolId: 1,
            academicYearId: 9,
            periodId: 4,
            name: 'فترة',
            startDate: '2025-09-01T08:00',
            endDate: '2025-09-30T16:00',
        ));
    }

    /**
     * @return array{
     *     id:int,
     *     school_id:int,
     *     academic_year_id:int,
     *     name:string,
     *     status:int,
     *     start_date:string,
     *     end_date:string,
     *     max_applications:?int
     * }
     */
    private function periodRow(): array
    {
        return [
            'id' => 4,
            'school_id' => 1,
            'academic_year_id' => 9,
            'name' => 'قديمة',
            'status' => 1,
            'start_date' => '2026-09-01 08:00:00',
            'end_date' => '2026-09-30 16:00:00',
            'max_applications' => 10,
        ];
    }

    private function handler(
        AdmissionRepositoryInterface $admission,
        ?OutboxRepository $outbox = null,
    ): UpdateApplicationPeriodHandler {
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

        return new UpdateApplicationPeriodHandler(
            $unitOfWork,
            $admission,
            $outbox ?? $this->createMock(OutboxRepository::class),
            $this->createMock(IdempotencyStore::class),
            new ApplicationPeriodAcademicYearGuard($years),
        );
    }
}
