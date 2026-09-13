<?php

namespace App\Application\Communication\Queries;

use App\Application\Communication\DTOs\MessageDTO;
use App\Domain\Communication\Repositories\MessageRepositoryInterface;

final class GetMessageHandler
{
    public function __construct(
        private readonly MessageRepositoryInterface $messages,
    ) {}

    public function handle(GetMessageQuery $query): ?MessageDTO
    {
        $s = $this->messages->findByIdForSchool($query->schoolId, $query->messageId);
        if ($s === null) {
            return null;
        }

        return new MessageDTO(
            id: $s->id,
            schoolId: $s->schoolId,
            templateId: $s->templateId,
            recipientType: $s->recipientType,
            recipientId: $s->recipientId,
            channel: $s->channel,
            subject: $s->subject,
            body: $s->body,
            status: $s->status,
            sentAt: $s->sentAt,
            createdAt: $s->createdAt,
        );
    }
}
