<?php

namespace App\Domain\Communication\Repositories;

use App\Domain\Communication\Data\NotificationJobSnapshot;

interface NotificationJobRepositoryInterface
{
    public function templateBelongsToSchool(int $schoolId, int $templateId): bool;

    /**
     * @param  array<string, mixed>  $targetFilter
     */
    public function create(
        int $schoolId,
        int $templateId,
        array $targetFilter,
        int $totalCount,
        int $status,
        string $idempotencyKey,
        ?int $createdBy,
        string $createdAt,
    ): int;

    public function findByIdForSchool(int $schoolId, int $jobId): ?NotificationJobSnapshot;

    /** @return list<NotificationJobSnapshot> */
    public function listForSchool(int $schoolId, ?int $status, int $limit): array;

    public function markCompleted(int $schoolId, int $id, int $sentCount, string $completedAt): bool;

    public function markCancelled(int $schoolId, int $id, string $completedAt): bool;

    public function markReopened(int $schoolId, int $id): bool;
}
