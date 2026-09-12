<?php

namespace App\Domain\Portal\Data;

final readonly class PortalScopeSnapshot
{
    public function __construct(
        public int $id,
        public int $userId,
        public string $scopeType,
        public int $scopeId,
        public string $createdAt,
    ) {}
}
