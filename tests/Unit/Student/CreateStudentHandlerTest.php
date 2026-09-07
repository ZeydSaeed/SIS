<?php

namespace Tests\Unit\Student;

use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Student\Commands\CreateStudentCommand;
use App\Application\Student\Commands\CreateStudentHandler;
use App\Domain\Student\Data\CreateStudentData;
use App\Domain\Student\Events\StudentRegistered;
use App\Domain\Student\Exceptions\StudentCodeAlreadyExistsException;
use App\Domain\Student\Repositories\StudentRepositoryInterface;
use PHPUnit\Framework\TestCase;

class CreateStudentHandlerTest extends TestCase
{
    public function test_creates_student_stages_outbox_and_returns_result(): void
    {
        $command = new CreateStudentCommand(
            firstName: 'Ali',
            middleName: 'Hassan',
            lastName: 'Karim',
            gender: 1,
            birthDate: '2010-05-15',
            studentCode: 'STU-UNIT-001',
        );

        $students = $this->createMock(StudentRepositoryInterface::class);
        $students->method('existsByCode')->willReturn(false);
        $students->method('existsByNationalId')->willReturn(false);
        $students->expects($this->once())
            ->method('saveNew')
            ->with($this->callback(fn (CreateStudentData $data): bool => $data->studentCode === 'STU-UNIT-001'
                && $data->fullName === 'Ali Hassan Karim'))
            ->willReturn(7);

        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork->method('transaction')->willReturnCallback(fn (callable $callback) => $callback());

        $outbox = $this->createMock(OutboxRepository::class);
        $outbox->expects($this->once())
            ->method('stage')
            ->with($this->isInstanceOf(StudentRegistered::class));

        $idempotency = $this->createMock(IdempotencyStore::class);

        $handler = new CreateStudentHandler($unitOfWork, $students, $outbox, $idempotency);
        $result = $handler->handle($command);

        $this->assertTrue($result->success);
        $this->assertSame(7, $result->studentId);
        $this->assertSame('STU-UNIT-001', $result->studentCode);
    }

    public function test_rejects_duplicate_student_code(): void
    {
        $students = $this->createMock(StudentRepositoryInterface::class);
        $students->method('existsByCode')->willReturn(true);

        $handler = new CreateStudentHandler(
            $this->createMock(UnitOfWork::class),
            $students,
            $this->createMock(OutboxRepository::class),
            $this->createMock(IdempotencyStore::class),
        );

        $this->expectException(StudentCodeAlreadyExistsException::class);

        $handler->handle(new CreateStudentCommand(
            firstName: 'Ali',
            middleName: null,
            lastName: 'Karim',
            gender: 1,
            birthDate: '2010-05-15',
            studentCode: 'STU-DUP',
        ));
    }
}
