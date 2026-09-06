<?php

namespace Tests\Unit\Enrollment;

use App\Application\Contracts\DomainEventDispatcher;
use App\Application\Contracts\UnitOfWork;
use App\Application\Enrollment\Commands\EnrollStudentCommand;
use App\Application\Enrollment\Commands\EnrollStudentHandler;
use App\Domain\Enrollment\Data\CreateEnrollmentData;
use App\Domain\Enrollment\Data\StudentEnrollmentView;
use App\Domain\Enrollment\Events\StudentEnrolled;
use App\Domain\Enrollment\Exceptions\EnrollmentDomainException;
use App\Domain\Enrollment\Repositories\EnrollmentRepositoryInterface;
use App\Domain\Enrollment\Repositories\StudentReadRepositoryInterface;
use App\Domain\Shared\DomainEvent;
use PHPUnit\Framework\TestCase;

class EnrollStudentHandlerTest extends TestCase
{
    public function test_enrolls_active_student_and_dispatches_event(): void
    {
        $student = new StudentEnrollmentView(1, 1, 'STU-001', 'Ali Hassan');
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
        $students->method('findForEnrollment')->with(1)->willReturn($student);

        $enrollments = $this->createMock(EnrollmentRepositoryInterface::class);
        $enrollments->method('hasActiveEnrollment')->willReturn(false);
        $enrollments->method('generateEnrollmentNumber')->willReturn('ENR-10-2026-000001');
        $enrollments->expects($this->once())
            ->method('save')
            ->with($this->callback(function (CreateEnrollmentData $data): bool {
                return $data->studentId === 1
                    && $data->enrollmentNumber === 'ENR-10-2026-000001';
            }))
            ->willReturn(42);

        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork->method('transaction')->willReturnCallback(
            fn (callable $callback) => $callback()
        );

        $dispatched = [];
        $events = $this->createMock(DomainEventDispatcher::class);
        $events->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (DomainEvent $event) use (&$dispatched): bool {
                $dispatched[] = $event;

                return $event instanceof StudentEnrolled
                    && $event->enrollmentId() === 42
                    && $event->enrollmentNumber() === 'ENR-10-2026-000001';
            }));

        $handler = new EnrollStudentHandler($unitOfWork, $enrollments, $students, $events);

        $this->assertSame(42, $handler->handle($command));
        $this->assertCount(1, $dispatched);
    }

    public function test_rejects_inactive_student(): void
    {
        $student = new StudentEnrollmentView(1, 0, 'STU-001', 'Ali Hassan');
        $students = $this->createMock(StudentReadRepositoryInterface::class);
        $students->method('findForEnrollment')->willReturn($student);

        $handler = new EnrollStudentHandler(
            $this->createMock(UnitOfWork::class),
            $this->createMock(EnrollmentRepositoryInterface::class),
            $students,
            $this->createMock(DomainEventDispatcher::class),
        );

        $this->expectException(EnrollmentDomainException::class);
        $handler->handle(new EnrollStudentCommand(10, 2026, 1, 5, 12, '2026-09-01'));
    }

    public function test_rejects_duplicate_enrollment(): void
    {
        $student = new StudentEnrollmentView(1, 1, 'STU-001', 'Ali Hassan');
        $students = $this->createMock(StudentReadRepositoryInterface::class);
        $students->method('findForEnrollment')->willReturn($student);

        $enrollments = $this->createMock(EnrollmentRepositoryInterface::class);
        $enrollments->method('hasActiveEnrollment')->willReturn(true);

        $handler = new EnrollStudentHandler(
            $this->createMock(UnitOfWork::class),
            $enrollments,
            $students,
            $this->createMock(DomainEventDispatcher::class),
        );

        $this->expectException(EnrollmentDomainException::class);
        $handler->handle(new EnrollStudentCommand(10, 2026, 1, 5, 12, '2026-09-01'));
    }
}
