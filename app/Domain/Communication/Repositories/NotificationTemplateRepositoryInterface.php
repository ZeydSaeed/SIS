<?php

namespace App\Domain\Communication\Repositories;

use App\Domain\Communication\Data\NotificationTemplateSnapshot;

interface NotificationTemplateRepositoryInterface
{
    public function create(
        int $schoolId,
        string $code,
        string $name,
        int $channel,
        ?string $subjectTemplate,
        string $bodyTemplate,
        bool $isActive,
        string $createdAt,
    ): int;

    public function findIdBySchoolAndCode(int $schoolId, string $code): ?int;

    public function find(int $schoolId, int $templateId): ?NotificationTemplateSnapshot;

    public function setActive(int $schoolId, int $templateId, bool $isActive): void;

    /**
     * @return list<NotificationTemplateSnapshot>
     */
    public function listBySchool(int $schoolId, ?bool $activeOnly = null): array;
}
