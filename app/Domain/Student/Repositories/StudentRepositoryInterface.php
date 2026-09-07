<?php

namespace App\Domain\Student\Repositories;

use App\Domain\Student\Data\CreateStudentData;
use App\Domain\Student\Data\UpdateStudentData;
use App\Domain\Student\Entities\Student;

interface StudentRepositoryInterface
{
    public function saveNew(CreateStudentData $data): int;

    public function update(int $studentId, UpdateStudentData $data): void;

    public function findById(int $studentId): ?Student;

    public function existsByCode(string $code, ?int $exceptStudentId = null): bool;

    public function existsByNationalId(string $nationalId, ?int $exceptStudentId = null): bool;

    public function generateStudentCode(): string;
}
