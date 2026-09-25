<?php

namespace App\Domain\Admission\Data;

final readonly class CreateApplicationDraftData
{
    public function __construct(
        public int $applicationPeriodId,
        public string $applicationNumber,
        public string $firstName,
        public string $fatherName,
        public string $grandfatherName,
        public string $greatGrandfatherName,
        public string $lastName,
        public string $motherName,
        public string $maternalFatherName,
        public string $maternalGrandfatherName,
        public string $birthDate,
        public string $birthPlace,
        public int $gender,
        public int $targetSchoolId,
        public string $intendedGradeName,
        public int $requestKind = 2,
        public ?string $nationalId = null,
        public ?int $gradeLevelId = null,
        public ?int $branchId = null,
        public ?string $branchName = null,
        public ?string $departmentName = null,
        public ?int $specializationId = null,
        public ?string $specializationName = null,
        public ?string $governorate = null,
        public ?int $administrativeUnit = null,
        public ?string $neighborhood = null,
        public ?string $fatherOccupation = null,
        public ?string $motherOccupation = null,
        public ?string $studentMobile = null,
        public ?string $guardianMobile = null,
        public ?string $previousSchoolName = null,
        public ?int $graduationYear = null,
        public ?string $previousGpa = null,
        public ?string $mathematicsGrade = null,
        public ?string $physicsGrade = null,
        public ?int $previousStudyTrack = null,
        public ?string $notes = null,
    ) {}
}
