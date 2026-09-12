<?php

namespace App\Application\Communication\Queries;

use App\Application\Communication\DTOs\NotificationTemplateDTO;
use App\Domain\Communication\Repositories\NotificationTemplateRepositoryInterface;

final class ListNotificationTemplatesHandler
{
    public function __construct(
        private readonly NotificationTemplateRepositoryInterface $templates,
    ) {}

    /**
     * @return list<NotificationTemplateDTO>
     */
    public function handle(ListNotificationTemplatesQuery $query): array
    {
        return array_map(
            static fn ($s): NotificationTemplateDTO => new NotificationTemplateDTO(
                id: $s->id,
                schoolId: $s->schoolId,
                code: $s->code,
                name: $s->name,
                channel: $s->channel,
                subjectTemplate: $s->subjectTemplate,
                bodyTemplate: $s->bodyTemplate,
                isActive: $s->isActive,
                createdAt: $s->createdAt,
            ),
            $this->templates->listBySchool($query->schoolId, $query->activeOnly),
        );
    }
}
