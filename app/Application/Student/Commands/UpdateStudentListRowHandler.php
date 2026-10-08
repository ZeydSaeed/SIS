<?php

namespace App\Application\Student\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Student\Results\UpdateStudentListRowResult;
use App\Application\Student\Services\StudentEnrollmentPlacementSync;
use App\Application\Student\Support\StudentNameFormatter;
use App\Domain\Student\Data\UpdateStudentData;
use App\Domain\Student\Events\StudentProfileUpdated;
use App\Domain\Student\Exceptions\DuplicateNationalIdException;
use App\Domain\Student\Exceptions\StudentNotFoundException;
use App\Domain\Student\Repositories\StudentRepositoryInterface;

final class UpdateStudentListRowHandler implements CommandHandler
{
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly StudentRepositoryInterface $students,
        private readonly OutboxRepository $outbox,
        private readonly StudentEnrollmentPlacementSync $placementSync,
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
            fatherName: $command->has('father_name') ? $command->fatherName : $current->fatherName,
            grandfatherName: $command->has('grandfather_name') ? $command->grandfatherName : $current->grandfatherName,
            greatGrandfatherName: $command->has('great_grandfather_name')
                ? $command->greatGrandfatherName
                : $current->greatGrandfatherName,
            middleName: $current->middleName,
        );

        $placement = $this->placementSync->prepare(
            $command->studentId,
            $command->schoolId,
            $current->admittedClassName,
            $command->requestedAdmittedClassName(),
        );

        $data = $this->mergedData($command, $current, $fullName);
        if ($data->nationalId !== null && $data->nationalId !== $current->nationalId
            && $this->students->existsByNationalId($data->nationalId, $command->studentId)) {
            throw DuplicateNationalIdException::forNationalId($data->nationalId);
        }

        $this->unitOfWork->transaction(function () use ($command, $data, $fullName, $placement): void {
            $this->students->update($command->studentId, $data);
            $this->placementSync->afterSave($command->studentId, $command->schoolId, $placement);
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
        $pickString = static function (string $field, ?string $incoming, ?string $existing) use ($command): ?string {
            return $command->has($field) ? $incoming : $existing;
        };
        $pickInt = static function (string $field, ?int $incoming, ?int $existing) use ($command): ?int {
            return $command->has($field) ? $incoming : $existing;
        };
        $pickFloat = static function (string $field, ?float $incoming, ?float $existing) use ($command): ?float {
            return $command->has($field) ? $incoming : $existing;
        };

        // Legacy group flags remain as fallback when presentFields is empty (tests / old callers).
        $useField = $command->presentFields !== [];
        $overlay = $command->applyFormFields;
        $pii = $command->applyPii;

        return new UpdateStudentData(
            firstName: $command->firstName,
            middleName: $current->middleName,
            fatherName: $useField
                ? $pickString('father_name', $command->fatherName, $current->fatherName)
                : $command->fatherName,
            grandfatherName: $useField
                ? $pickString('grandfather_name', $command->grandfatherName, $current->grandfatherName)
                : $command->grandfatherName,
            greatGrandfatherName: $useField
                ? $pickString('great_grandfather_name', $command->greatGrandfatherName, $current->greatGrandfatherName)
                : $command->greatGrandfatherName,
            motherName: $useField
                ? $pickString('mother_name', $command->motherName, $current->motherName)
                : ($overlay ? $command->motherName : $current->motherName),
            maternalFatherName: $useField
                ? $pickString('maternal_father_name', $command->maternalFatherName, $current->maternalFatherName)
                : ($overlay ? $command->maternalFatherName : $current->maternalFatherName),
            maternalGrandfatherName: $useField
                ? $pickString('maternal_grandfather_name', $command->maternalGrandfatherName, $current->maternalGrandfatherName)
                : ($overlay ? $command->maternalGrandfatherName : $current->maternalGrandfatherName),
            lastName: $command->lastName,
            fullName: $fullName,
            gender: $useField
                ? ($command->has('gender') && $command->gender !== null ? $command->gender : $current->gender)
                : ($command->gender !== null ? $command->gender : $current->gender),
            birthDate: $command->birthDate,
            nationalId: $useField
                ? $pickString('national_id', $command->nationalId, $current->nationalId)
                : ($pii ? $command->nationalId : $current->nationalId),
            birthPlace: $useField
                ? $pickString('birth_place', $command->birthPlace, $current->birthPlace)
                : ($overlay ? $command->birthPlace : $current->birthPlace),
            nationality: $useField
                ? $pickString('nationality', $command->nationality, $current->nationality)
                : ($overlay ? $command->nationality : $current->nationality),
            guardianTripleName: $useField
                ? $pickString('guardian_triple_name', $command->guardianTripleName, $current->guardianTripleName)
                : ($overlay ? $command->guardianTripleName : $current->guardianTripleName),
            governorate: $useField
                ? $pickString('governorate', $command->governorate, $current->governorate)
                : ($overlay ? $command->governorate : $current->governorate),
            neighborhood: $useField
                ? $pickString('neighborhood', $command->neighborhood, $current->neighborhood)
                : ($overlay ? $command->neighborhood : $current->neighborhood),
            locality: $useField
                ? $pickString('locality', $command->locality, $current->locality)
                : ($overlay ? $command->locality : $current->locality),
            houseNumber: $useField
                ? $pickString('house_number', $command->houseNumber, $current->houseNumber)
                : ($overlay ? $command->houseNumber : $current->houseNumber),
            registrationPlace: $useField
                ? $pickString('registration_place', $command->registrationPlace, $current->registrationPlace)
                : ($overlay ? $command->registrationPlace : $current->registrationPlace),
            religion: $useField
                ? ($command->has('religion') && $command->religion !== null ? $command->religion : $current->religion)
                : ($overlay && $command->religion !== null ? $command->religion : $current->religion),
            mawalidDate: $useField
                ? $pickString('mawalid_date', $command->mawalidDate, $current->mawalidDate)
                : ($overlay ? $command->mawalidDate : $current->mawalidDate),
            previousSchoolName: $useField
                ? $pickString('previous_school_name', $command->previousSchoolName, $current->previousSchoolName)
                : ($overlay ? $command->previousSchoolName : $current->previousSchoolName),
            transferDocumentNumber: $useField
                ? $pickInt('transfer_document_number', $command->transferDocumentNumber, $current->transferDocumentNumber)
                : ($overlay ? $command->transferDocumentNumber : $current->transferDocumentNumber),
            transferDocumentDate: $useField
                ? $pickString('transfer_document_date', $command->transferDocumentDate, $current->transferDocumentDate)
                : ($overlay ? $command->transferDocumentDate : $current->transferDocumentDate),
            schoolStartDate: $useField
                ? $pickString('school_start_date', $command->schoolStartDate, $current->schoolStartDate)
                : ($overlay ? $command->schoolStartDate : $current->schoolStartDate),
            admittedClassName: $useField
                ? ($command->has('admitted_class_name')
                    ? ($command->admittedClassName ?? $current->admittedClassName)
                    : $current->admittedClassName)
                : ($command->admittedClassName ?? $current->admittedClassName),
            notes: $useField
                ? $pickString('notes', $command->notes, $current->notes)
                : ($overlay ? $command->notes : $current->notes),
            mobile: $useField
                ? $pickString('mobile', $command->mobile, $current->mobile)
                : ($pii ? $command->mobile : $current->mobile),
            guardianMobile: $useField
                ? $pickString('guardian_mobile', $command->guardianMobile, $current->guardianMobile)
                : ($pii ? $command->guardianMobile : $current->guardianMobile),
            email: $useField
                ? $pickString('email', $command->email, $current->email)
                : ($pii ? $command->email : $current->email),
            schoolName: $useField
                ? $pickString('school_name', $command->schoolName, $current->schoolName)
                : ($overlay ? $command->schoolName : $current->schoolName),
            branchId: $useField
                ? $pickInt('branch_id', $command->branchId, $current->branchId)
                : ($command->branchId !== null ? $command->branchId : $current->branchId),
            departmentName: $useField
                ? ($command->has('department_name')
                    ? ($command->departmentName ?? $current->departmentName)
                    : $current->departmentName)
                : ($command->departmentName ?? $current->departmentName),
            fatherOccupation: $useField
                ? $pickString('father_occupation', $command->fatherOccupation, $current->fatherOccupation)
                : ($overlay ? $command->fatherOccupation : $current->fatherOccupation),
            motherOccupation: $useField
                ? $pickString('mother_occupation', $command->motherOccupation, $current->motherOccupation)
                : ($overlay ? $command->motherOccupation : $current->motherOccupation),
            administrativeUnit: $useField
                ? $pickInt('administrative_unit', $command->administrativeUnit, $current->administrativeUnit)
                : ($overlay && $command->administrativeUnit !== null ? $command->administrativeUnit : $current->administrativeUnit),
            graduationYear: $useField
                ? $pickInt('graduation_year', $command->graduationYear, $current->graduationYear)
                : ($overlay && $command->graduationYear !== null ? $command->graduationYear : $current->graduationYear),
            previousGpa: $useField
                ? $pickFloat('previous_gpa', $command->previousGpa, $current->previousGpa)
                : ($overlay && $command->previousGpa !== null ? $command->previousGpa : $current->previousGpa),
            previousStudyTrack: $useField
                ? $pickInt('previous_study_track', $command->previousStudyTrack, $current->previousStudyTrack)
                : ($overlay && $command->previousStudyTrack !== null ? $command->previousStudyTrack : $current->previousStudyTrack),
            mathematicsGrade: $useField
                ? $pickFloat('mathematics_grade', $command->mathematicsGrade, $current->mathematicsGrade)
                : ($overlay && $command->mathematicsGrade !== null ? $command->mathematicsGrade : $current->mathematicsGrade),
            physicsGrade: $useField
                ? $pickFloat('physics_grade', $command->physicsGrade, $current->physicsGrade)
                : ($overlay && $command->physicsGrade !== null ? $command->physicsGrade : $current->physicsGrade),
            admittedAcademicYearId: $useField
                ? ($command->has('academic_year_id')
                    ? ($command->admittedAcademicYearId ?? $current->admittedAcademicYearId)
                    : $current->admittedAcademicYearId)
                : ($command->admittedAcademicYearId !== null
                    ? $command->admittedAcademicYearId
                    : $current->admittedAcademicYearId),
        );
    }
}
