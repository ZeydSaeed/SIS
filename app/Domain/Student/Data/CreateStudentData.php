<?php

namespace App\Domain\Student\Data;

use App\Domain\Student\ValueObjects\StudentReligion;

final readonly class CreateStudentData
{
    public function __construct(
        public string $studentCode,
        public string $firstName,
        public ?string $middleName,
        public ?string $fatherName,
        public ?string $grandfatherName,
        public ?string $greatGrandfatherName,
        public ?string $motherName,
        public ?string $maternalFatherName,
        public ?string $maternalGrandfatherName,
        public string $lastName,
        public string $fullName,
        public int $gender,
        public string $birthDate,
        public ?string $nationalId = null,
        public ?string $birthPlace = null,
        public ?string $nationality = null,
        public ?string $guardianTripleName = null,
        public ?string $governorate = null,
        public ?string $neighborhood = null,
        public ?string $locality = null,
        public ?string $houseNumber = null,
        public ?string $registrationPlace = null,
        public int $religion = StudentReligion::Muslim->value,
        public ?string $mawalidDate = null,
        public ?string $previousSchoolName = null,
        public ?int $transferDocumentNumber = null,
        public ?string $transferDocumentDate = null,
        public ?string $schoolStartDate = null,
        public ?string $admittedClassName = null,
        public ?string $notes = null,
        public ?string $mobile = null,
        public ?string $guardianMobile = null,
        public ?string $email = null,
        public ?string $schoolName = null,
        public ?int $branchId = null,
        public ?string $departmentName = null,
        public ?string $specializationName = null,
        public ?string $stageName = null,
        public ?string $sectionName = null,
        public int $status = 1,
        public ?int $schoolId = null,
        public ?int $admittedAcademicYearId = null,
    ) {}
}
