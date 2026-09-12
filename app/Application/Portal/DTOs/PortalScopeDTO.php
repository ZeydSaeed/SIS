<?php

namespace App\Application\Portal\DTOs;

final readonly class PortalScopeDTO
{
    public function __construct(
        public int $id,
        public int $userId,
        public string $scopeType,
        public int $scopeId,
        public string $createdAt,
    ) {}
}
