<?php

namespace App\Application\Portal\Queries;

final readonly class GetPortalScopeQuery
{
    public function __construct(public int $scopeRowId) {}
}
