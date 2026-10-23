<?php

namespace App\Domain\Exams\Repositories;

use App\Domain\Exams\Data\ExamTypeSnapshot;

/** The exam types of one school; `inUse` = at least one exam (any status) references the type. */
interface ExamTypeRepositoryInterface
{
    /** @return list<ExamTypeSnapshot> */
    public function listForSchool(int $schoolId): array;

    public function lockForSchool(int $examTypeId, int $schoolId): ?ExamTypeSnapshot;

    public function codeTaken(int $schoolId, string $code, ?int $exceptId): bool;

    public function insert(int $schoolId, string $code, string $name, int $weightPercentage): int;

    public function update(int $examTypeId, int $schoolId, string $code, string $name, int $weightPercentage): void;
}
