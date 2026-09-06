<?php

namespace App\Application\Enrollment\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Enrollment\Results\EnrollStudentResult;
use App\Domain\Enrollment\Data\CreateEnrollmentData;
use App\Domain\Enrollment\Events\StudentEnrolled;
use App\Domain\Enrollment\Exceptions\StudentAlreadyEnrolledException;
use App\Domain\Enrollment\Exceptions\StudentInactiveException;
use App\Domain\Enrollment\Repositories\EnrollmentRepositoryInterface;
use App\Domain\Enrollment\Repositories\StudentReadRepositoryInterface;
use App\Domain\Enrollment\Specifications\EligibleForEnrollmentSpecification;
use App\Domain\Student\Exceptions\StudentNotFoundException;

final class EnrollStudentHandler implements CommandHandler
{
    private const COMMAND_NAME = 'EnrollStudent';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly EnrollmentRepositoryInterface $enrollments,
        private readonly StudentReadRepositoryInterface $students,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
        private readonly EligibleForEnrollmentSpecification $eligibility = new EligibleForEnrollmentSpecification,
    ) {}

    public function handle(Command $command): EnrollStudentResult
    {
        assert($command instanceof EnrollStudentCommand);

        if ($command->idempotencyKey !== null) {
            $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
            if ($cached !== null) {
                return EnrollStudentResult::fromIdempotency(
                    (int) $cached['enrollment_id'],
                    (string) $cached['enrollment_number'],
                );
            }
        }

        $student = $this->students->findById($command->studentId);
        if ($student === null) {
            throw StudentNotFoundException::forId($command->studentId);
        }

        $reasons = $this->eligibility->unsatisfiedReasons($student);
        if ($reasons !== []) {
            throw StudentInactiveException::withReasons($reasons);
        }

        if ($this->enrollments->hasActiveEnrollment($command->studentId, $command->academicYearId)) {
            throw StudentAlreadyEnrolledException::forYear($command->studentId, $command->academicYearId);
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

            $this->outbox->stage(new StudentEnrolled(
                enrollmentId: $enrollmentId,
                studentId: $command->studentId,
                schoolId: $command->schoolId,
                academicYearId: $command->academicYearId,
                classId: $command->classId,
                sectionId: $command->sectionId,
                enrollmentNumber: $enrollmentNumber,
                enrolledBy: $command->enrolledBy,
                occurredAt: new \DateTimeImmutable,
            ));

            return ['id' => $enrollmentId, 'enrollment_number' => $enrollmentNumber];
        });

        if ($command->idempotencyKey !== null) {
            $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, [
                'enrollment_id' => $result['id'],
                'enrollment_number' => $result['enrollment_number'],
            ]);
        }

        return EnrollStudentResult::success($result['id'], $result['enrollment_number']);
    }
}
