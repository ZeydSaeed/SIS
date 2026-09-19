<?php

namespace Tests\Unit\Admission;

use App\Application\Admission\Commands\ChangeApplicationPeriodStatusCommand;
use App\Application\Admission\Commands\ChangeApplicationPeriodStatusHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Domain\Admission\Events\ApplicationPeriodStatusChanged;
use App\Domain\Admission\Exceptions\ApplicationPeriodNotFoundException;
use App\Domain\Admission\Repositories\AdmissionRepositoryInterface;
use App\Domain\Admission\ValueObjects\ApplicationPeriodStatus;
use PHPUnit\Framework\TestCase;

class ChangeApplicationPeriodStatusHandlerTest extends TestCase
{
    public function test_changes_status_and_stages_outbox_event(): void
    {
        $admission = $this->createMock(AdmissionRepositoryInterface::class);
        $admission->method('findPeriodForSchool')->willReturn($this->periodRow(ApplicationPeriodStatus::Active->value));
        $admission->expects($this->once())->method('updatePeriodStatus')->with(4, ApplicationPeriodStatus::Archived->value);

        $outbox = $this->createMock(OutboxRepository::class);
        $outbox->expects($this->once())->method('stage')->with($this->isInstanceOf(ApplicationPeriodStatusChanged::class));

        $result = $this->handler($admission, $outbox)->handle(new ChangeApplicationPeriodStatusCommand(
            schoolId: 1,
            academicYearId: 9,
            periodId: 4,
            status: ApplicationPeriodStatus::Archived->value,
        ));

        $this->assertTrue($result->success);
        $this->assertSame(ApplicationPeriodStatus::Active->value, $result->fromStatus);
        $this->assertSame(ApplicationPeriodStatus::Archived->value, $result->toStatus);
    }

    public function test_same_status_skips_write(): void
    {
        $admission = $this->createMock(AdmissionRepositoryInterface::class);
        $admission->method('findPeriodForSchool')->willReturn($this->periodRow(ApplicationPeriodStatus::Active->value));
        $admission->expects($this->never())->method('updatePeriodStatus');

        $result = $this->handler($admission)->handle(new ChangeApplicationPeriodStatusCommand(
            schoolId: 1,
            academicYearId: 9,
            periodId: 4,
            status: ApplicationPeriodStatus::Active->value,
        ));

        $this->assertTrue($result->success);
        $this->assertSame(ApplicationPeriodStatus::Active->value, $result->fromStatus);
        $this->assertSame(ApplicationPeriodStatus::Active->value, $result->toStatus);
    }

    public function test_rejects_unknown_period(): void
    {
        $admission = $this->createMock(AdmissionRepositoryInterface::class);
        $admission->method('findPeriodForSchool')->willReturn(null);

        $this->expectException(ApplicationPeriodNotFoundException::class);
        $this->handler($admission)->handle(new ChangeApplicationPeriodStatusCommand(
            schoolId: 1,
            academicYearId: 9,
            periodId: 99,
            status: ApplicationPeriodStatus::Inactive->value,
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
    private function periodRow(int $status): array
    {
        return [
            'id' => 4,
            'school_id' => 1,
            'academic_year_id' => 9,
            'name' => 'فترة',
            'status' => $status,
            'start_date' => '2026-09-01 08:00:00',
            'end_date' => '2026-09-30 16:00:00',
            'max_applications' => null,
        ];
    }

    private function handler(
        AdmissionRepositoryInterface $admission,
        ?OutboxRepository $outbox = null,
    ): ChangeApplicationPeriodStatusHandler {
        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork->method('transaction')->willReturnCallback(fn (callable $callback) => $callback());

        return new ChangeApplicationPeriodStatusHandler(
            $unitOfWork,
            $admission,
            $outbox ?? $this->createMock(OutboxRepository::class),
            $this->createMock(IdempotencyStore::class),
        );
    }
}
