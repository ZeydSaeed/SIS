<?php

namespace App\Application\Enrollment\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\DomainEventDispatcher;
use App\Application\Contracts\UnitOfWork;
use App\Domain\Enrollment\Data\CreateEnrollmentData;
use App\Domain\Enrollment\Events\StudentEnrolled;
use App\Domain\Enrollment\Exceptions\EnrollmentDomainException;
use App\Domain\Enrollment\Repositories\EnrollmentRepositoryInterface;
use App\Domain\Enrollment\Repositories\StudentReadRepositoryInterface;
use App\Domain\Enrollment\Specifications\EligibleForEnrollmentSpecification;

final class EnrollStudentHandler implements CommandHandler
{
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly EnrollmentRepositoryInterface $enrollments,
        private readonly StudentReadRepositoryInterface $students,
        private readonly DomainEventDispatcher $events,
        private readonly EligibleForEnrollmentSpecification $eligibility = new EligibleForEnrollmentSpecification,
    ) {}

    public function handle(Command $command): int
    {
        assert($command instanceof EnrollStudentCommand);

        $student = $this->students->findForEnrollment($command->studentId);
        if ($student === null) {
            throw EnrollmentDomainException::studentNotFound($command->studentId);
        }

        $reasons = $this->eligibility->unsatisfiedReasons($student);
        if ($reasons !== []) {
            throw EnrollmentDomainException::notEligible($reasons);
        }

        if ($this->enrollments->hasActiveEnrollment($command->studentId, $command->academicYearId)) {
            throw EnrollmentDomainException::alreadyEnrolled($command->studentId, $command->academicYearId);
        }

        $result = $this->unitOfWork->transaction(function () use ($command): array {
            $enrollmentNumber = $this->enrollments->generateEnrollmentNumber(
                $command->schoolId,
                $command->academicYearId,
            );

            $enrollmentId = $this->enrollments->save(new CreateEnrollmentData(
                studentId: $command->studentId,
                academicYearId: $command->academicYearId,
                schoolId: $command->schoolId,
                classId: $command->classId,
                sectionId: $command->sectionId,
                enrollmentNumber: $enrollmentNumber,
                effectiveFrom: $command->effectiveFrom,
                specializationId: $command->specializationId,
                enrolledBy: $command->enrolledBy,
            ));

            return ['id' => $enrollmentId, 'enrollment_number' => $enrollmentNumber];
        });

        $this->events->dispatch(new StudentEnrolled(
            enrollmentId: $result['id'],
            studentId: $command->studentId,
            schoolId: $command->schoolId,
            academicYearId: $command->academicYearId,
            classId: $command->classId,
            sectionId: $command->sectionId,
            enrollmentNumber: $result['enrollment_number'],
            enrolledBy: $command->enrolledBy,
            occurredAt: new \DateTimeImmutable,
        ));

        return $result['id'];
    }
}
