<?php

namespace Tests\Unit\Student;

use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Student\Commands\ChangeStudentStatusesCommand;
use App\Application\Student\Commands\ChangeStudentStatusesHandler;
use App\Domain\Shared\Exceptions\SisDomainException;
use App\Domain\Student\Entities\Student;
use App\Domain\Student\Events\StudentStatusChanged;
use App\Domain\Student\Exceptions\StudentNotFoundException;
use App\Domain\Student\Repositories\StudentRepositoryInterface;
use App\Domain\Student\ValueObjects\StudentCode;
use App\Domain\Student\ValueObjects\StudentStatus;
use PHPUnit\Framework\TestCase;

class ChangeStudentStatusesHandlerTest extends TestCase
{
    public function test_updates_selected_students_in_school_and_stages_outbox_events(): void
    {
        $students = $this->createMock(StudentRepositoryInterface::class);
        $students->method('findByIdForSchool')->willReturnCallback(
            fn (int $id): Student => Student::reconstitute(
                $id,
                new StudentCode(sprintf('STU-%03d', $id)),
                'Student '.$id,
                StudentStatus::Active,
            ),
        );
        $students->expects($this->exactly(2))->method('updateStatus')->with(
            $this->logicalOr($this->equalTo(11), $this->equalTo(12)),
            StudentStatus::Suspended->value,
        );

        $outbox = $this->createMock(OutboxRepository::class);
        $outbox->expects($this->exactly(2))->method('stage')->with($this->isInstanceOf(StudentStatusChanged::class));

        $result = $this->handler($students, $outbox)->handle(new ChangeStudentStatusesCommand(
            schoolId: 4,
            studentIds: [11, 12, 12],
            status: StudentStatus::Suspended->value,
        ));

        $this->assertTrue($result->success);
        $this->assertSame([11, 12], $result->studentIds);
        $this->assertSame(StudentStatus::Suspended->value, $result->status);
        $this->assertSame(2, $result->count);
    }

    public function test_rejects_empty_selection(): void
    {
        $students = $this->createMock(StudentRepositoryInterface::class);
        $students->expects($this->never())->method('updateStatus');

        $this->expectException(SisDomainException::class);
        $this->handler($students)->handle(new ChangeStudentStatusesCommand(
            schoolId: 4,
            studentIds: [],
            status: StudentStatus::Active->value,
        ));
    }

    public function test_rejects_unknown_or_cross_school_student(): void
    {
        $students = $this->createMock(StudentRepositoryInterface::class);
        $students->method('findByIdForSchool')->willReturn(null);

        $this->expectException(StudentNotFoundException::class);
        $this->handler($students)->handle(new ChangeStudentStatusesCommand(
            schoolId: 4,
            studentIds: [99],
            status: StudentStatus::Inactive->value,
        ));
    }

    private function handler(
        StudentRepositoryInterface $students,
        ?OutboxRepository $outbox = null,
    ): ChangeStudentStatusesHandler {
        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork->method('transaction')->willReturnCallback(fn (callable $callback) => $callback());

        return new ChangeStudentStatusesHandler(
            $unitOfWork,
            $students,
            $outbox ?? $this->createMock(OutboxRepository::class),
            $this->createMock(IdempotencyStore::class),
        );
    }
}
