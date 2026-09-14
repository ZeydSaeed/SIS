<?php

namespace App\Application\Portal\Queries;

use App\Application\Portal\DTOs\PortalScopeDTO;
use App\Domain\Portal\Repositories\PortalScopeRepositoryInterface;

final class GetPortalScopeHandler
{
    public function __construct(
        private readonly PortalScopeRepositoryInterface $scopes,
    ) {}

    public function handle(GetPortalScopeQuery $query): ?PortalScopeDTO
    {
        $row = $this->scopes->findById($query->scopeRowId);

        if ($row === null) {
            return null;
        }

        return new PortalScopeDTO(
            $row->id,
            $row->userId,
            $row->scopeType,
            $row->scopeId,
            $row->createdAt,
        );
    }
}
