<?php

namespace App\Application\Teachers\Commands;

use App\Application\Contracts\Command;

final readonly class RegisterTeacherCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public string $employeeCode,
        public string $firstName,
        public string $lastName,
        public ?string $nationalId,
        public ?string $specializationField,
        public ?string $hireDate,
        public ?int $userId,
        public ?string $idempotencyKey,
        public ?string $fatherName = null,
        public ?string $grandfatherName = null,
        /** نوع التعيين in the registering school (TeacherEmploymentType); null = not set. */
        public ?int $employmentType = null,
    ) {}
}
