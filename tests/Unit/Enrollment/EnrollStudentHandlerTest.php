<?php

namespace Tests\Unit\Enrollment;

use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Enrollment\Commands\EnrollStudentCommand;
use App\Application\Enrollment\Commands\EnrollStudentHandler;
use App\Domain\Enrollment\Data\CreateEnrollmentData;
use App\Domain\Enrollment\Events\StudentEnrolled;
use App\Domain\Enrollment\Exceptions\StudentAlreadyEnrolledException;
use App\Domain\Enrollment\Exceptions\StudentInactiveException;
use App\Domain\Enrollment\Repositories\EnrollmentRepositoryInterface;
use App\Domain\Enrollment\Repositories\StudentReadRepositoryInterface;
use App\Domain\Student\Entities\Student;
use App\Domain\Student\Exceptions\StudentNotFoundException;
use App\Domain\Student\ValueObjects\StudentCode;
use App\Domain\Student\ValueObjects\StudentStatus;
use PHPUnit\Framework\TestCase;

class EnrollStudentHandlerTest extends TestCase
{
    public function test_enrolls_active_student_stages_outbox_and_returns_result(): void
    {
        $student = Student::reconstitute(1, new StudentCode('STU-001'), 'Ali Hassan', StudentStatus::Active);
        $command = new EnrollStudentCommand(
            schoolId: 10,
            academicYearId: 2026,
            studentId: 1,
            classId: 5,
            sectionId: 12,
            effectiveFrom: '2026-09-01',
            enrolledBy: 99,
        );

        $students = $this->createMock(StudentReadRepositoryInterface::class);
        $students->method('findById')->with(1)->willReturn($student);

        $enrollments = $this->createMock(EnrollmentRepositoryInterface::class);
        $enrollments->method('hasActiveEnrollment')->willReturn(false);
        $enrollments->method('generateEnrollmentNumber')->willReturn('ENR-10-2026-000001');
        $enrollments->expects($this->once())
            ->method('save')
            ->with($this->callback(fn (CreateEnrollmentData $data) => $data->studentId === 1))
            ->willReturn(42);

        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork->method('transaction')->willReturnCallback(fn (callable $callback) => $callback());

        $outbox = $this->createMock(OutboxRepository::class);
        $outbox->expects($this->once())
            ->method('stage')
            ->with($this->isInstanceOf(StudentEnrolled::class));

        $idempotency = $this->createMock(IdempotencyStore::class);
        $idempotency->expects($this->never())->method('store');

        $handler = new EnrollStudentHandler($unitOfWork, $enrollments, $students, $outbox, $idempotency);

        $result = $handler->handle($command);

        $this->assertTrue($result->success);
        $this->assertSame(42, $result->enrollmentId);
        $this->assertSame('ENR-10-2026-000001', $result->enrollmentNumber);
    }

    public function test_returns_cached_result_when_idempotency_key_exists(): void
    {
        $students = $this->createMock(StudentReadRepositoryInterface::class);
        $students->expects($this->never())->method('findById');

        $idempotency = $this->createMock(IdempotencyStore::class);
        $idempotency->method('find')->willReturn([
            'enrollment_id' => 42,
            'enrollment_number' => 'ENR-10-2026-000001',
        ]);

        $handler = new EnrollStudentHandler(
            $this->createMock(UnitOfWork::class),
            $this->createMock(EnrollmentRepositoryInterface::class),
            $students,
            $this->createMock(OutboxRepository::class),
            $idempotency,
        );

        $result = $handler->handle(new EnrollStudentCommand(
            10, 2026, 1, 5, 12, '2026-09-01', idempotencyKey: 'key-123',
        ));

        $this->assertTrue($result->fromIdempotencyCache);
        $this->assertSame(42, $result->enrollmentId);
    }

    public function test_rejects_inactive_student(): void
    {
        $student = Student::reconstitute(1, new StudentCode('STU-001'), 'Ali Hassan', StudentStatus::Suspended);
        $students = $this->createMock(StudentReadRepositoryInterface::class);
        $students->method('findById')->willReturn($student);

        $handler = new EnrollStudentHandler(
            $this->createMock(UnitOfWork::class),
            $this->createMock(EnrollmentRepositoryInterface::class),
            $students,
            $this->createMock(OutboxRepository::class),
            $this->createMock(IdempotencyStore::class),
        );

        $this->expectException(StudentInactiveException::class);
        $handler->handle(new EnrollStudentCommand(10, 2026, 1, 5, 12, '2026-09-01'));
    }

    public function test_rejects_duplicate_enrollment(): void
    {
        $student = Student::reconstitute(1, new StudentCode('STU-001'), 'Ali Hassan', StudentStatus::Active);
        $students = $this->createMock(StudentReadRepositoryInterface::class);
        $students->method('findById')->willReturn($student);

        $enrollments = $this->createMock(EnrollmentRepositoryInterface::class);
        $enrollments->method('hasActiveEnrollment')->willReturn(true);

        $handler = new EnrollStudentHandler(
            $this->createMock(UnitOfWork::class),
            $enrollments,
            $students,
            $this->createMock(OutboxRepository::class),
            $this->createMock(IdempotencyStore::class),
        );

        $this->expectException(StudentAlreadyEnrolledException::class);
        $handler->handle(new EnrollStudentCommand(10, 2026, 1, 5, 12, '2026-09-01'));
    }

    public function test_throws_when_student_not_found(): void
    {
        $students = $this->createMock(StudentReadRepositoryInterface::class);
        $students->method('findById')->willReturn(null);

        $handler = new EnrollStudentHandler(
            $this->createMock(UnitOfWork::class),
            $this->createMock(EnrollmentRepositoryInterface::class),
            $students,
            $this->createMock(OutboxRepository::class),
            $this->createMock(IdempotencyStore::class),
        );

        $this->expectException(StudentNotFoundException::class);
        $handler->handle(new EnrollStudentCommand(10, 2026, 1, 5, 12, '2026-09-01'));
    }
}
