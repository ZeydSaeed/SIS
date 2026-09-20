<?php

namespace App\Application\Student\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Student\Results\UpdateStudentListRowResult;
use App\Application\Student\Support\StudentNameFormatter;
use App\Domain\Student\Data\UpdateStudentData;
use App\Domain\Student\Events\StudentProfileUpdated;
use App\Domain\Student\Exceptions\StudentNotFoundException;
use App\Domain\Student\Repositories\StudentRepositoryInterface;

final class UpdateStudentListRowHandler implements CommandHandler
{
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly StudentRepositoryInterface $students,
        private readonly OutboxRepository $outbox,
    ) {}

    public function handle(Command $command): UpdateStudentListRowResult
    {
        assert($command instanceof UpdateStudentListRowCommand);

        $existing = $this->students->findByIdForSchool($command->studentId, $command->schoolId);
        if ($existing === null) {
            throw StudentNotFoundException::forId($command->studentId);
        }

        $current = $this->students->findUpdateData($command->studentId, $command->schoolId);
        if ($current === null) {
            throw StudentNotFoundException::forId($command->studentId);
        }

        $fullName = StudentNameFormatter::fullName(
            firstName: $command->firstName,
            lastName: $command->lastName,
            fatherName: $command->fatherName,
            grandfatherName: $command->grandfatherName,
            greatGrandfatherName: $command->greatGrandfatherName,
            middleName: $current->middleName,
        );

        $this->unitOfWork->transaction(function () use ($command, $current, $fullName): void {
            $this->students->update($command->studentId, $this->mergedData($command, $current, $fullName));
            $this->outbox->stage(new StudentProfileUpdated(
                studentId: $command->studentId,
                fullName: $fullName,
                occurredAt: new \DateTimeImmutable,
            ));
        });

        return UpdateStudentListRowResult::success($command->studentId);
    }

    private function mergedData(
        UpdateStudentListRowCommand $command,
        UpdateStudentData $current,
        string $fullName,
    ): UpdateStudentData {
        $overlay = $command->applyFormFields;
        $pii = $command->applyPii;

        return new UpdateStudentData(
            firstName: $command->firstName,
            middleName: $current->middleName,
            fatherName: $command->fatherName,
            grandfatherName: $command->grandfatherName,
            greatGrandfatherName: $command->greatGrandfatherName,
            motherName: $overlay ? $command->motherName : $current->motherName,
            maternalFatherName: $overlay ? $command->maternalFatherName : $current->maternalFatherName,
            maternalGrandfatherName: $overlay ? $command->maternalGrandfatherName : $current->maternalGrandfatherName,
            lastName: $command->lastName,
            fullName: $fullName,
            gender: $overlay && $command->gender !== null ? $command->gender : $current->gender,
            birthDate: $command->birthDate,
            nationalId: $pii ? $command->nationalId : $current->nationalId,
            birthPlace: $overlay ? $command->birthPlace : $current->birthPlace,
            nationality: $overlay ? $command->nationality : $current->nationality,
            guardianTripleName: $overlay ? $command->guardianTripleName : $current->guardianTripleName,
            governorate: $overlay ? $command->governorate : $current->governorate,
            neighborhood: $overlay ? $command->neighborhood : $current->neighborhood,
            locality: $overlay ? $command->locality : $current->locality,
            houseNumber: $overlay ? $command->houseNumber : $current->houseNumber,
            registrationPlace: $overlay ? $command->registrationPlace : $current->registrationPlace,
            religion: $overlay && $command->religion !== null ? $command->religion : $current->religion,
            mawalidDate: $overlay ? $command->mawalidDate : $current->mawalidDate,
            previousSchoolName: $overlay ? $command->previousSchoolName : $current->previousSchoolName,
            transferDocumentNumber: $overlay ? $command->transferDocumentNumber : $current->transferDocumentNumber,
            transferDocumentDate: $overlay ? $command->transferDocumentDate : $current->transferDocumentDate,
            schoolStartDate: $overlay ? $command->schoolStartDate : $current->schoolStartDate,
            admittedClassName: $command->admittedClassName,
            notes: $overlay ? $command->notes : $current->notes,
            mobile: $pii ? $command->mobile : $current->mobile,
            guardianMobile: $pii ? $command->guardianMobile : $current->guardianMobile,
            email: $pii ? $command->email : $current->email,
            schoolName: $overlay ? $command->schoolName : $current->schoolName,
            departmentName: $command->departmentName,
            specializationName: $command->specializationName,
            stageName: $overlay ? $command->stageName : $current->stageName,
            sectionName: $overlay ? $command->sectionName : $current->sectionName,
        );
    }
}
