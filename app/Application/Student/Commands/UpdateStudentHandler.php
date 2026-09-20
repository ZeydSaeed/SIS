<?php

namespace App\Application\Student\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Student\Results\UpdateStudentResult;
use App\Application\Student\Support\StudentNameFormatter;
use App\Domain\Student\Data\UpdateStudentData;
use App\Domain\Student\Events\StudentProfileUpdated;
use App\Domain\Student\Exceptions\DuplicateNationalIdException;
use App\Domain\Student\Exceptions\StudentNotFoundException;
use App\Domain\Student\Repositories\StudentRepositoryInterface;

final class UpdateStudentHandler implements CommandHandler
{
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly StudentRepositoryInterface $students,
        private readonly OutboxRepository $outbox,
    ) {}

    public function handle(Command $command): UpdateStudentResult
    {
        assert($command instanceof UpdateStudentCommand);

        $existing = $this->students->findById($command->studentId);
        if ($existing === null) {
            throw StudentNotFoundException::forId($command->studentId);
        }

        if ($command->nationalId !== null && $this->students->existsByNationalId($command->nationalId, $command->studentId)) {
            throw DuplicateNationalIdException::forNationalId($command->nationalId);
        }

        $fullName = StudentNameFormatter::fullName(
            firstName: $command->firstName,
            lastName: $command->lastName,
            fatherName: $command->fatherName,
            grandfatherName: $command->grandfatherName,
            greatGrandfatherName: $command->greatGrandfatherName,
            middleName: $command->middleName,
        );

        $this->unitOfWork->transaction(function () use ($command, $fullName): void {
            $this->students->update($command->studentId, new UpdateStudentData(
                firstName: $command->firstName,
                middleName: $command->middleName,
                fatherName: $command->fatherName,
                grandfatherName: $command->grandfatherName,
                greatGrandfatherName: $command->greatGrandfatherName,
                motherName: $command->motherName,
                maternalFatherName: $command->maternalFatherName,
                maternalGrandfatherName: $command->maternalGrandfatherName,
                lastName: $command->lastName,
                fullName: $fullName,
                gender: $command->gender,
                birthDate: $command->birthDate,
                nationalId: $command->nationalId,
                birthPlace: $command->birthPlace,
                nationality: $command->nationality,
                guardianTripleName: $command->guardianTripleName,
                governorate: $command->governorate,
                neighborhood: $command->neighborhood,
                locality: $command->locality,
                houseNumber: $command->houseNumber,
                registrationPlace: $command->registrationPlace,
                religion: $command->religion,
                mawalidDate: $command->mawalidDate,
                previousSchoolName: $command->previousSchoolName,
                transferDocumentNumber: $command->transferDocumentNumber,
                transferDocumentDate: $command->transferDocumentDate,
                schoolStartDate: $command->schoolStartDate,
                admittedClassName: $command->admittedClassName,
                notes: $command->notes,
                mobile: $command->mobile,
                guardianMobile: $command->guardianMobile,
                email: $command->email,
                schoolName: $command->schoolName,
                departmentName: $command->departmentName,
                specializationName: $command->specializationName,
                stageName: $command->stageName,
                sectionName: $command->sectionName,
            ));

            $this->outbox->stage(new StudentProfileUpdated(
                studentId: $command->studentId,
                fullName: $fullName,
                occurredAt: new \DateTimeImmutable,
            ));
        });

        return UpdateStudentResult::success($command->studentId);
    }
}
