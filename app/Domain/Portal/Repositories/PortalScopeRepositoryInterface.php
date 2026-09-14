<?php

namespace App\Domain\Portal\Repositories;

use App\Domain\Portal\Data\PortalScopeSnapshot;

interface PortalScopeRepositoryInterface
{
    public function userExists(int $userId): bool;

    public function studentExists(int $studentId): bool;

    public function guardianExists(int $guardianId): bool;

    public function studentHasEnrollmentInSchool(int $studentId, int $schoolId): bool;

    public function guardianLinkedToStudentInSchool(int $guardianId, int $schoolId): bool;

    public function findScopeId(int $userId, string $scopeType, int $scopeId): ?int;

    public function insertScope(int $userId, string $scopeType, int $scopeId, string $createdAt): int;

    public function deleteScope(int $userId, string $scopeType, int $scopeId): bool;

    /**
     * @return list<PortalScopeSnapshot>
     */
    public function listForUser(int $userId): array;

    public function findById(int $scopeRowId): ?PortalScopeSnapshot;
}
