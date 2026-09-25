<?php

namespace Tests\Unit\Admission;

use App\Application\Admission\Commands\BulkTransitionApplicationStatusCommand;
use App\Application\Admission\Commands\BulkTransitionApplicationStatusHandler;
use App\Application\Admission\Support\AcceptedApplicationStudentConverter;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Domain\Admission\Events\ApplicationStatusTransitioned;
use App\Domain\Admission\Exceptions\ApplicationNotFoundException;
use App\Domain\Admission\Exceptions\InvalidApplicationTransitionException;
use App\Domain\Admission\Repositories\AdmissionRepositoryInterface;
use App\Domain\Admission\Services\BulkApplicationTransitionGuard;
use App\Domain\Admission\ValueObjects\ApplicationStatus;
use DomainException;
use PHPUnit\Framework\TestCase;

class BulkTransitionApplicationStatusHandlerTest extends TestCase
{
    public function test_transitions_selected_applications_and_stages_outbox_events(): void
    {
        $admission = $this->createMock(AdmissionRepositoryInterface::class);
        $admission->method('findApplicationForSchool')->willReturnCallback(
            fn (int $id): array => $this->applicationRow($id, ApplicationStatus::Draft->value),
        );
        $admission->expects($this->exactly(2))->method('transitionApplicationStatus')->with(
            $this->logicalOr($this->equalTo(11), $this->equalTo(12)),
            ApplicationStatus::Submitted->value,
            7,
            null,
        );

        $outbox = $this->createMock(OutboxRepository::class);
        $outbox->expects($this->exactly(2))->method('stage')->with($this->isInstanceOf(ApplicationStatusTransitioned::class));

        $result = $this->handler($admission, $outbox)->handle(new BulkTransitionApplicationStatusCommand(
            schoolId: 1,
            applicationIds: [11, 12, 12],
            toStatus: ApplicationStatus::Submitted->value,
            reviewedBy: 7,
        ));

        $this->assertTrue($result->success);
        $this->assertSame([11, 12], $result->applicationIds);
        $this->assertSame(ApplicationStatus::Submitted->value, $result->toStatus);
        $this->assertSame(2, $result->count);
    }

    public function test_accept_converts_each_application_to_student(): void
    {
        $admission = $this->createMock(AdmissionRepositoryInterface::class);
        $admission->method('findApplicationForSchool')->willReturnCallback(
            fn (int $id): array => $this->applicationRow($id, ApplicationStatus::UnderReview->value),
        );
        $admission->expects($this->exactly(2))->method('transitionApplicationStatus');

        $convert = $this->createMock(AcceptedApplicationStudentConverter::class);
        $convert->expects($this->exactly(2))->method('convert')->with(
            $this->equalTo(1),
            $this->logicalOr($this->equalTo(11), $this->equalTo(12)),
            $this->equalTo(7),
            $this->isNull(),
        );

        $result = $this->handler($admission, convert: $convert)->handle(new BulkTransitionApplicationStatusCommand(
            schoolId: 1,
            applicationIds: [11, 12],
            toStatus: ApplicationStatus::Accepted->value,
            reviewedBy: 7,
        ));

        $this->assertTrue($result->success);
        $this->assertSame(ApplicationStatus::Accepted->value, $result->toStatus);
    }

    public function test_accept_reverts_status_when_convert_fails(): void
    {
        $calls = [];
        $admission = $this->createMock(AdmissionRepositoryInterface::class);
        $admission->method('findApplicationForSchool')->willReturn(
            $this->applicationRow(11, ApplicationStatus::UnderReview->value),
        );
        $admission->expects($this->exactly(2))->method('transitionApplicationStatus')->willReturnCallback(
            function (int $id, int $status) use (&$calls): void {
                $calls[] = [$id, $status];
            },
        );

        $convert = $this->createMock(AcceptedApplicationStudentConverter::class);
        $convert->expects($this->once())->method('convert')->willThrowException(
            new DomainException('تعذر التحويل'),
        );

        try {
            $this->handler($admission, convert: $convert)->handle(new BulkTransitionApplicationStatusCommand(
                schoolId: 1,
                applicationIds: [11],
                toStatus: ApplicationStatus::Accepted->value,
                reviewedBy: 7,
            ));
            $this->fail('Expected DomainException was not thrown.');
        } catch (DomainException $exception) {
            $this->assertSame('تعذر التحويل', $exception->getMessage());
        }

        $this->assertSame([
            [11, ApplicationStatus::Accepted->value],
            [11, ApplicationStatus::UnderReview->value],
        ], $calls);
    }

    public function test_rejects_empty_selection(): void
    {
        $admission = $this->createMock(AdmissionRepositoryInterface::class);
        $admission->expects($this->never())->method('transitionApplicationStatus');

        $this->expectException(DomainException::class);
        $this->handler($admission)->handle(new BulkTransitionApplicationStatusCommand(
            schoolId: 1,
            applicationIds: [],
            toStatus: ApplicationStatus::Submitted->value,
        ));
    }

    public function test_rejects_unknown_application(): void
    {
        $admission = $this->createMock(AdmissionRepositoryInterface::class);
        $admission->method('findApplicationForSchool')->willReturn(null);

        $this->expectException(ApplicationNotFoundException::class);
        $this->handler($admission)->handle(new BulkTransitionApplicationStatusCommand(
            schoolId: 1,
            applicationIds: [99],
            toStatus: ApplicationStatus::Submitted->value,
        ));
    }

    public function test_rejects_invalid_transition(): void
    {
        $admission = $this->createMock(AdmissionRepositoryInterface::class);
        $admission->method('findApplicationForSchool')->willReturn(
            $this->applicationRow(11, ApplicationStatus::Draft->value),
        );
        $admission->expects($this->never())->method('transitionApplicationStatus');

        $this->expectException(InvalidApplicationTransitionException::class);
        $this->handler($admission)->handle(new BulkTransitionApplicationStatusCommand(
            schoolId: 1,
            applicationIds: [11],
            toStatus: ApplicationStatus::Rejected->value,
        ));
    }

    public function test_rejects_converted_target(): void
    {
        $admission = $this->createMock(AdmissionRepositoryInterface::class);
        $admission->expects($this->never())->method('findApplicationForSchool');

        $this->expectException(InvalidApplicationTransitionException::class);
        $this->handler($admission)->handle(new BulkTransitionApplicationStatusCommand(
            schoolId: 1,
            applicationIds: [11],
            toStatus: ApplicationStatus::Converted->value,
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
     *     grade_level_id:int,
     *     specialization_id:?int,
     *     status:int,
     *     notes:?string,
     *     student_id:?int,
     *     school_id:int
     * }
     */
    private function applicationRow(int $id, int $status): array
    {
        return [
            'id' => $id,
            'application_period_id' => 3,
            'application_number' => 'APP-1-9-000'.$id,
            'first_name' => 'أحمد',
            'last_name' => 'علي',
            'national_id' => null,
            'birth_date' => '2015-01-01',
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
        ?AcceptedApplicationStudentConverter $convert = null,
    ): BulkTransitionApplicationStatusHandler {
        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork->method('transaction')->willReturnCallback(
            static fn (callable $callback) => $callback(),
        );

        if ($convert === null) {
            $convert = $this->createMock(AcceptedApplicationStudentConverter::class);
            $convert->expects($this->never())->method('convert');
        }

        return new BulkTransitionApplicationStatusHandler(
            $unitOfWork,
            $admission,
            new BulkApplicationTransitionGuard($admission),
            $outbox ?? $this->createMock(OutboxRepository::class),
            $this->createMock(IdempotencyStore::class),
            $convert,
        );
    }
}
