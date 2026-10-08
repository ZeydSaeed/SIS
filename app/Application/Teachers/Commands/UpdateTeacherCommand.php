<?php

namespace App\Application\Teachers\Commands;

use App\Application\Contracts\Command;

final readonly class UpdateTeacherCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $teacherId,
        public string $firstName,
        public string $lastName,
        public ?string $nationalId,
        public ?string $specializationField,
        public ?string $hireDate,
        public ?int $userId,
        public ?string $idempotencyKey,
        public ?string $fatherName = null,
        public ?string $grandfatherName = null,
        /** When set, نوع التعيين of the membership in this school/year is updated too. */
        public ?int $academicYearId = null,
        public ?int $employmentType = null,
        /** When true the personal lesson limits below replace the stored ones (null = no limit). */
        public bool $updateWorkload = false,
        public ?int $weeklyLessonsMin = null,
        public ?int $weeklyLessonsMax = null,
        public ?int $dailyLessonsMax = null,
    ) {}
}
