<?php

namespace App\Domain\Student\Repositories;

use App\Domain\Student\Data\StudentDocumentSnapshot;

interface StudentDocumentRepositoryInterface
{
    public function studentInSchool(int $schoolId, int $studentId): bool;

    public function create(
        int $schoolId,
        int $studentId,
        int $documentType,
        string $storageKey,
        string $fileName,
        string $mimeType,
        int $fileSize,
        string $fileHash,
        ?int $uploadedBy,
        string $createdAt,
    ): int;

    public function findActive(int $schoolId, int $documentId): ?StudentDocumentSnapshot;

    public function findVoided(int $schoolId, int $documentId): ?StudentDocumentSnapshot;

    public function find(int $schoolId, int $documentId): ?StudentDocumentSnapshot;

    /** @return list<StudentDocumentSnapshot> */
    public function listActiveForStudent(int $schoolId, int $studentId): array;

    public function void(int $schoolId, int $documentId): bool;

    public function restore(int $schoolId, int $documentId): bool;
}
