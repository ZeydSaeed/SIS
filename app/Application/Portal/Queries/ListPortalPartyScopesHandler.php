<?php

namespace App\Application\Portal\Queries;

use App\Application\Portal\DTOs\PortalScopeDTO;
use App\Domain\Portal\Repositories\PortalScopeRepositoryInterface;

final class ListPortalPartyScopesHandler
{
    public function __construct(
        private readonly PortalScopeRepositoryInterface $scopes,
    ) {}

    /**
     * @return list<PortalScopeDTO>|null null when user missing
     */
    public function handle(ListPortalPartyScopesQuery $query): ?array
    {
        if (! $this->scopes->userExists($query->userId)) {
            return null;
        }

        return array_map(
            static fn ($s): PortalScopeDTO => new PortalScopeDTO(
                $s->id,
                $s->userId,
                $s->scopeType,
                $s->scopeId,
                $s->createdAt,
            ),
            $this->scopes->listForUser($query->userId),
        );
    }
}
