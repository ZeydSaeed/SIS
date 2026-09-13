<?php

namespace App\Domain\Communication\Repositories;

use App\Domain\Communication\Data\MessageSnapshot;

interface MessageRepositoryInterface
{
    public function templateBelongsToSchool(int $schoolId, int $templateId): bool;

    public function create(
        int $schoolId,
        ?int $templateId,
        string $recipientType,
        int $recipientId,
        int $channel,
        ?string $subject,
        string $body,
        int $status,
        string $idempotencyKey,
        string $createdAt,
    ): int;

    public function findByIdForSchool(int $schoolId, int $messageId): ?MessageSnapshot;

    public function markSent(int $schoolId, int $messageId, int $status, string $sentAt): bool;

    /**
     * @return list<MessageSnapshot>
     */
    public function listBySchool(
        int $schoolId,
        ?string $recipientType = null,
        ?int $recipientId = null,
        ?int $status = null,
    ): array;
}
