<?php

namespace App\Application\Admission\Commands;

use App\Application\Admission\Results\ConvertApplicationToStudentResult;
use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Student\Support\StudentNameFormatter;
use App\Domain\Admission\Events\ApplicationConvertedToStudent;
use App\Domain\Admission\Exceptions\ApplicationNotConvertibleException;
use App\Domain\Admission\Exceptions\ApplicationNotFoundException;
use App\Domain\Admission\Repositories\AdmissionRepositoryInterface;
use App\Domain\Admission\ValueObjects\ApplicationStatus;
use App\Domain\Student\Data\CreateStudentData;
use App\Domain\Student\Events\StudentRegistered;
use App\Domain\Student\Exceptions\DuplicateNationalIdException;
use App\Domain\Student\Repositories\StudentRepositoryInterface;

final class ConvertApplicationToStudentHandler implements CommandHandler
{
    private const COMMAND_NAME = 'ConvertApplicationToStudent';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly AdmissionRepositoryInterface $admission,
        private readonly StudentRepositoryInterface $students,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): ConvertApplicationToStudentResult
    {
        assert($command instanceof ConvertApplicationToStudentCommand);

        if ($command->idempotencyKey !== null) {
            $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
            if ($cached !== null) {
                return ConvertApplicationToStudentResult::fromIdempotency(
                    (int) $cached['application_id'],
                    (int) $cached['student_id'],
                );
            }
        }

        $application = $this->admission->findApplicationForSchool($command->applicationId, $command->schoolId);
        if ($application === null) {
            throw ApplicationNotFoundException::forId($command->applicationId);
        }

        $status = ApplicationStatus::from($application['status']);
        if (! $status->canConvertToStudent()) {
            throw ApplicationNotConvertibleException::forStatus($application['status']);
        }

        if ($application['national_id'] !== null && $this->students->existsByNationalId($application['national_id'])) {
            throw DuplicateNationalIdException::forNationalId($application['national_id']);
        }

        $studentCode = $this->students->generateStudentCode();
        $fullName = StudentNameFormatter::fullName(
            firstName: $application['first_name'],
            lastName: $application['last_name'],
            fatherName: $application['father_name'] ?? null,
            grandfatherName: $application['grandfather_name'] ?? null,
            greatGrandfatherName: $application['great_grandfather_name'] ?? null,
        );

        $studentId = $this->unitOfWork->transaction(function () use ($command, $application, $studentCode, $fullName): int {
            $id = $this->students->saveNew(new CreateStudentData(
                studentCode: $studentCode,
                firstName: $application['first_name'],
                middleName: null,
                fatherName: $application['father_name'] ?? null,
                grandfatherName: $application['grandfather_name'] ?? null,
                greatGrandfatherName: $application['great_grandfather_name'] ?? null,
                motherName: $application['mother_name'] ?? null,
                maternalFatherName: $application['maternal_father_name'] ?? null,
                maternalGrandfatherName: $application['maternal_grandfather_name'] ?? null,
                lastName: $application['last_name'],
                fullName: $fullName,
                gender: $application['gender'],
                birthDate: $application['birth_date'],
                nationalId: $application['national_id'],
                birthPlace: $application['birth_place'] ?? null,
                governorate: $application['governorate'] ?? null,
                neighborhood: $application['neighborhood'] ?? null,
                admittedClassName: $application['intended_grade_name'] ?? null,
                notes: $application['notes'] ?? null,
                schoolName: $application['school_name'] ?? null,
                branchId: isset($application['branch_id']) ? (int) $application['branch_id'] : null,
                departmentName: $application['department_name'] ?? null,
                specializationName: $application['specialization_name'] ?? null,
                schoolId: $command->schoolId,
                admittedAcademicYearId: $application['academic_year_id'],
            ));

            $this->admission->markConverted($command->applicationId, $id, $command->reviewedBy);

            $this->outbox->stage(new StudentRegistered(
                studentId: $id,
                studentCode: $studentCode,
                fullName: $fullName,
                occurredAt: new \DateTimeImmutable,
            ));

            $this->outbox->stage(new ApplicationConvertedToStudent(
                applicationId: $command->applicationId,
                studentId: $id,
                schoolId: $command->schoolId,
                applicationNumber: $application['application_number'],
                occurredAt: new \DateTimeImmutable,
            ));

            return $id;
        });

        if ($command->idempotencyKey !== null) {
            $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, [
                'application_id' => $command->applicationId,
                'student_id' => $studentId,
            ]);
        }

        return ConvertApplicationToStudentResult::success($command->applicationId, $studentId);
    }
}
