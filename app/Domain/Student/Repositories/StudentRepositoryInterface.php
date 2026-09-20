<?php

namespace App\Domain\Student\Repositories;

use App\Domain\Student\Data\CreateStudentData;
use App\Domain\Student\Data\UpdateStudentData;
use App\Domain\Student\Entities\Student;

interface StudentRepositoryInterface
{
    public function saveNew(CreateStudentData $data): int;

    public function update(int $studentId, UpdateStudentData $data): void;

    public function findUpdateData(int $studentId, int $schoolId): ?UpdateStudentData;

    public function findById(int $studentId): ?Student;

    public function findByIdForSchool(int $studentId, int $schoolId): ?Student;

    public function updateStatus(int $studentId, int $status): void;

    public function existsByCode(string $code, ?int $exceptStudentId = null): bool;

    public function existsByNationalId(string $nationalId, ?int $exceptStudentId = null): bool;

    public function generateStudentCode(): string;
}
