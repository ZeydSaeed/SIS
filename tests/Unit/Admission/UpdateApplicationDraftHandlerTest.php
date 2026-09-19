<?php

namespace Tests\Unit\Admission;

use App\Application\Admission\Commands\UpdateApplicationDraftCommand;
use App\Application\Admission\Commands\UpdateApplicationDraftHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Domain\Admission\Data\UpdateApplicationDraftData;
use App\Domain\Admission\Events\ApplicationDraftUpdated;
use App\Domain\Admission\Exceptions\ApplicationNotFoundException;
use App\Domain\Admission\Repositories\AdmissionRepositoryInterface;
use App\Domain\Admission\ValueObjects\ApplicationStatus;
use DomainException;
use PHPUnit\Framework\TestCase;

class UpdateApplicationDraftHandlerTest extends TestCase
{
    public function test_updates_draft_and_stages_outbox_event(): void
    {
        $admission = $this->createMock(AdmissionRepositoryInterface::class);
        $admission->method('findApplicationForSchool')->willReturn($this->applicationRow());
        $admission->expects($this->once())
            ->method('updateDraft')
            ->with($this->callback(fn (UpdateApplicationDraftData $data): bool => $data->applicationId === 12
                && $data->notes === 'ملاحظة مراجعة'
                && $data->reviewedAt === '2026-09-19'));

        $outbox = $this->createMock(OutboxRepository::class);
        $outbox->expects($this->once())->method('stage')->with($this->isInstanceOf(ApplicationDraftUpdated::class));

        $result = $this->handler($admission, $outbox)->handle(new UpdateApplicationDraftCommand(
            schoolId: 1,
            applicationId: 12,
            notes: 'ملاحظة مراجعة',
            reviewedAt: '2026-09-19',
        ));

        $this->assertTrue($result->success);
        $this->assertSame(12, $result->applicationId);
    }

    public function test_rejects_unknown_application(): void
    {
        $admission = $this->createMock(AdmissionRepositoryInterface::class);
        $admission->method('findApplicationForSchool')->willReturn(null);

        $this->expectException(ApplicationNotFoundException::class);
        $this->handler($admission)->handle(new UpdateApplicationDraftCommand(
            schoolId: 1,
            applicationId: 99,
            notes: null,
            reviewedAt: null,
        ));
    }

    public function test_updates_submitted_and_stages_outbox_event(): void
    {
        $admission = $this->createMock(AdmissionRepositoryInterface::class);
        $admission->method('findApplicationForSchool')->willReturn($this->applicationRow(
            status: ApplicationStatus::Submitted->value,
        ));
        $admission->expects($this->once())->method('updateDraft');

        $outbox = $this->createMock(OutboxRepository::class);
        $outbox->expects($this->once())->method('stage')->with($this->isInstanceOf(ApplicationDraftUpdated::class));

        $result = $this->handler($admission, $outbox)->handle(new UpdateApplicationDraftCommand(
            schoolId: 1,
            applicationId: 12,
            notes: 'ملاحظة مرسل',
            reviewedAt: null,
        ));

        $this->assertTrue($result->success);
        $this->assertSame(12, $result->applicationId);
    }

    public function test_rejects_terminal_status(): void
    {
        $admission = $this->createMock(AdmissionRepositoryInterface::class);
        $admission->method('findApplicationForSchool')->willReturn($this->applicationRow(
            status: ApplicationStatus::Converted->value,
        ));

        $this->expectException(DomainException::class);
        $this->handler($admission)->handle(new UpdateApplicationDraftCommand(
            schoolId: 1,
            applicationId: 12,
            notes: 'x',
            reviewedAt: null,
        ));
    }

    /**
     * @return array{
     *     id:int,
     *     application_period_id:int,
     *     application_number:string,
     *     first_name:string,
     *     last_name:string,
     *     national_id:?string,
     *     birth_date:string,
     *     gender:int,
     *     grade_level_id:?int,
     *     specialization_id:?int,
     *     status:int,
     *     notes:?string,
     *     student_id:?int,
     *     school_id:int
     * }
     */
    private function applicationRow(int $status = 1): array
    {
        return [
            'id' => 12,
            'application_period_id' => 3,
            'application_number' => 'APP-1-9-0001',
            'first_name' => 'أحمد',
            'last_name' => 'علي',
            'national_id' => null,
            'birth_date' => '2010-01-01',
            'gender' => 1,
            'grade_level_id' => 1,
            'specialization_id' => null,
            'status' => $status,
            'notes' => null,
            'student_id' => null,
            'school_id' => 1,
        ];
    }

    private function handler(
        AdmissionRepositoryInterface $admission,
        ?OutboxRepository $outbox = null,
    ): UpdateApplicationDraftHandler {
        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork->method('transaction')->willReturnCallback(
            static fn (callable $callback) => $callback(),
        );

        return new UpdateApplicationDraftHandler(
            $unitOfWork,
            $admission,
            $outbox ?? $this->createMock(OutboxRepository::class),
            $this->createMock(IdempotencyStore::class),
        );
    }
}
