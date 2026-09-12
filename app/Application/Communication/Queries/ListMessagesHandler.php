<?php

namespace App\Application\Communication\Queries;

use App\Application\Communication\DTOs\MessageDTO;
use App\Domain\Communication\Repositories\MessageRepositoryInterface;

final class ListMessagesHandler
{
    public function __construct(
        private readonly MessageRepositoryInterface $messages,
    ) {}

    /**
     * @return list<MessageDTO>
     */
    public function handle(ListMessagesQuery $query): array
    {
        return array_map(
            static fn ($s): MessageDTO => new MessageDTO(
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
            ),
            $this->messages->listBySchool(
                $query->schoolId,
                $query->recipientType,
                $query->recipientId,
                $query->messageStatus,
            ),
        );
    }
}
