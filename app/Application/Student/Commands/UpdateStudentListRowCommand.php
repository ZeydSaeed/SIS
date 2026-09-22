<?php

namespace App\Application\Student\Commands;

use App\Application\Contracts\Command;

final readonly class UpdateStudentListRowCommand implements Command
{
    public function __construct(
        public int $studentId,
        public int $schoolId,
        public string $firstName,
        public string $lastName,
        public string $birthDate,
        public ?string $fatherName = null,
        public ?string $grandfatherName = null,
        public ?string $greatGrandfatherName = null,
        public ?string $departmentName = null,
        public ?string $specializationName = null,
        public ?string $admittedClassName = null,
        public bool $applyFormFields = false,
        public bool $applyPii = false,
        public ?string $motherName = null,
        public ?string $maternalFatherName = null,
        public ?string $maternalGrandfatherName = null,
        public ?string $guardianTripleName = null,
        public ?string $governorate = null,
        public ?string $neighborhood = null,
        public ?string $locality = null,
        public ?string $houseNumber = null,
        public ?string $birthPlace = null,
        public ?string $registrationPlace = null,
        public ?int $gender = null,
        public ?string $nationality = null,
        public ?int $religion = null,
        public ?string $mawalidDate = null,
        public ?string $previousSchoolName = null,
        public ?int $transferDocumentNumber = null,
        public ?string $transferDocumentDate = null,
        public ?string $schoolStartDate = null,
        public ?string $notes = null,
        public ?string $schoolName = null,
        public ?int $branchId = null,
        public ?string $stageName = null,
        public ?string $sectionName = null,
        public ?string $nationalId = null,
        public ?string $mobile = null,
        public ?string $guardianMobile = null,
        public ?string $email = null,
        public ?int $admittedAcademicYearId = null,
    ) {}
}
