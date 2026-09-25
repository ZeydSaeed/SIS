<?php

namespace App\Application\Admission\Support;

use App\Application\Admission\Commands\RegisterStudentViaAdmissionCommand;
use App\Domain\Admission\Data\CreateApplicationDraftData;
use App\Domain\Student\Data\CreateStudentData;

/** Maps fast-track admission form payload → persistence DTOs. */
final class RegisterStudentViaAdmissionMapper
{
    public static function toApplicationDraft(
        RegisterStudentViaAdmissionCommand $command,
        string $applicationNumber,
    ): CreateApplicationDraftData {
        return new CreateApplicationDraftData(
            applicationPeriodId: $command->applicationPeriodId,
            applicationNumber: $applicationNumber,
            firstName: $command->firstName,
            fatherName: $command->fatherName,
            grandfatherName: $command->grandfatherName,
            greatGrandfatherName: $command->greatGrandfatherName,
            lastName: $command->lastName,
            motherName: $command->motherName,
            maternalFatherName: $command->maternalFatherName,
            maternalGrandfatherName: $command->maternalGrandfatherName,
            birthDate: $command->birthDate,
            birthPlace: $command->birthPlace,
            gender: $command->gender,
            targetSchoolId: $command->targetSchoolId,
            intendedGradeName: $command->intendedGradeName,
            nationalId: $command->nationalId,
            gradeLevelId: $command->gradeLevelId,
            branchId: $command->branchId,
            branchName: $command->branchName,
            departmentName: $command->departmentName,
            specializationId: $command->specializationId,
            specializationName: $command->specializationName,
            governorate: $command->governorate,
            administrativeUnit: $command->administrativeUnit,
            neighborhood: $command->neighborhood,
            fatherOccupation: $command->fatherOccupation,
            motherOccupation: $command->motherOccupation,
            studentMobile: $command->studentMobile,
            guardianMobile: $command->guardianMobile,
            previousSchoolName: $command->previousSchoolName,
            graduationYear: $command->graduationYear,
            previousGpa: $command->previousGpa,
            previousStudyTrack: $command->previousStudyTrack,
            notes: $command->notes,
        );
    }

    public static function toCreateStudentData(
        RegisterStudentViaAdmissionCommand $command,
        string $studentCode,
        string $fullName,
        int $academicYearId,
        ?string $schoolName,
    ): CreateStudentData {
        return new CreateStudentData(
            studentCode: $studentCode,
            firstName: $command->firstName,
            middleName: null,
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
            governorate: $command->governorate,
            neighborhood: $command->neighborhood,
            admittedClassName: $command->intendedGradeName,
            notes: $command->notes,
            schoolName: $schoolName,
            branchId: $command->branchId,
            departmentName: $command->departmentName,
            specializationName: $command->specializationName,
            schoolId: $command->schoolId,
            admittedAcademicYearId: $academicYearId,
        );
    }
}
