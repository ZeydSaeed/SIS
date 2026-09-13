<?php

namespace App\Application\Communication\Queries;

use App\Application\Communication\DTOs\NotificationTemplateDTO;
use App\Domain\Communication\Repositories\NotificationTemplateRepositoryInterface;

final class GetNotificationTemplateHandler
{
    public function __construct(
        private readonly NotificationTemplateRepositoryInterface $templates,
    ) {}

    public function handle(GetNotificationTemplateQuery $query): ?NotificationTemplateDTO
    {
        $s = $this->templates->find($query->schoolId, $query->templateId);
        if ($s === null) {
            return null;
        }

        return new NotificationTemplateDTO(
            id: $s->id,
            schoolId: $s->schoolId,
            code: $s->code,
            name: $s->name,
            channel: $s->channel,
            subjectTemplate: $s->subjectTemplate,
            bodyTemplate: $s->bodyTemplate,
            isActive: $s->isActive,
            createdAt: $s->createdAt,
        );
    }
}
