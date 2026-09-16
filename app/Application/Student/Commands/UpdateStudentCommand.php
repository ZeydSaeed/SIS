<?php

namespace App\Application\Student\Commands;

use App\Application\Contracts\Command;
use App\Domain\Student\ValueObjects\StudentReligion;

final readonly class UpdateStudentCommand implements Command
{
    public function __construct(
        public int $studentId,
        public string $firstName,
        public string $lastName,
        public int $gender,
        public string $birthDate,
        public ?string $middleName = null,
        public ?string $fatherName = null,
        public ?string $grandfatherName = null,
        public ?string $greatGrandfatherName = null,
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
        public ?string $departmentName = null,
        public ?string $stageName = null,
        public ?string $sectionName = null,
    ) {}
}
