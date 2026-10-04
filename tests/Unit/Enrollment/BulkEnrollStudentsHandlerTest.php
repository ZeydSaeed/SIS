<?php

namespace Tests\Unit\Enrollment;

use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Enrollment\Commands\BulkEnrollStudentsCommand;
use App\Application\Enrollment\Commands\BulkEnrollStudentsHandler;
use App\Application\Enrollment\Commands\EnrollStudentHandler;
use App\Domain\Enrollment\Repositories\EnrollmentPlacementRepositoryInterface;
use App\Domain\Enrollment\Repositories\EnrollmentRepositoryInterface;
use App\Domain\Enrollment\Repositories\StudentReadRepositoryInterface;
use App\Domain\Student\Entities\Student;
use App\Domain\Student\ValueObjects\StudentCode;
use App\Domain\Student\ValueObjects\StudentStatus;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class BulkEnrollStudentsHandlerTest extends TestCase
{
    public function test_enrolls_eligible_students_and_reports_skipped_with_error_codes(): void
    {
        $students = $this->createMock(StudentReadRepositoryInterface::class);
        $students->method('findById')->willReturnCallback(
            static fn (int $id) => Student::reconstitute($id, new StudentCode('STU-'.$id), 'Student '.$id, StudentStatus::Active),
        );

        $enrollments = $this->createMock(EnrollmentRepositoryInterface::class);
        // Student 2 already has an active enrollment for the year.
        $enrollments->method('hasActiveEnrollment')->willReturnCallback(static fn (int $studentId) => $studentId === 2);
        $enrollments->method('generateEnrollmentNumber')->willReturn('ENR-1');
        $enrollments->expects($this->exactly(2))->method('save')->willReturnOnConsecutiveCalls(101, 103);

        $handler = new BulkEnrollStudentsHandler(
            $this->enrollHandler($enrollments, $students, $this->validPlacement()),
        );

        $result = $handler->handle(new BulkEnrollStudentsCommand(
            schoolId: 10,
            academicYearId: 2026,
            studentIds: [1, 2, 3, 3],
            classId: 5,
            sectionId: 12,
            effectiveFrom: '2026-09-01',
        ));

        $this->assertTrue($result->success);
        $this->assertSame([1, 3], $result->enrolledStudentIds);
        $this->assertSame([['student_id' => 2, 'error_code' => 'enrollment.already_enrolled']], $result->skipped);
    }

    public function test_capacity_reached_skips_student_with_capacity_code(): void
    {
        $students = $this->createMock(StudentReadRepositoryInterface::class);
        $students->method('findById')->willReturn(
            Student::reconstitute(1, new StudentCode('STU-1'), 'Student 1', StudentStatus::Active),
        );

        $enrollments = $this->createMock(EnrollmentRepositoryInterface::class);
        $enrollments->method('hasActiveEnrollment')->willReturn(false);
        $enrollments->expects($this->never())->method('save');

        $placement = $this->validPlacement();
        $placement->method('placementIsFull')->willReturn(true);

        $handler = new BulkEnrollStudentsHandler($this->enrollHandler($enrollments, $students, $placement));

        $result = $handler->handle(new BulkEnrollStudentsCommand(10, 2026, [1], 5, 12, '2026-09-01'));

        $this->assertSame([], $result->enrolledStudentIds);
        $this->assertSame([['student_id' => 1, 'error_code' => 'enrollment.capacity_reached']], $result->skipped);
    }

    private function validPlacement(): EnrollmentPlacementRepositoryInterface&MockObject
    {
        $placement = $this->createMock(EnrollmentPlacementRepositoryInterface::class);
        $placement->method('studentBelongsToSchool')->willReturn(true);
        $placement->method('classBelongsToSchool')->willReturn(true);
        $placement->method('sectionBelongsToClass')->willReturn(true);

        return $placement;
    }

    private function enrollHandler(
        EnrollmentRepositoryInterface $enrollments,
        StudentReadRepositoryInterface $students,
        EnrollmentPlacementRepositoryInterface $placement,
    ): EnrollStudentHandler {
        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork->method('transaction')->willReturnCallback(fn (callable $callback) => $callback());

        return new EnrollStudentHandler(
            $unitOfWork,
            $enrollments,
            $students,
            $this->createMock(OutboxRepository::class),
            $this->createMock(IdempotencyStore::class),
            $placement,
        );
    }
}
