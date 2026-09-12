<?php

namespace App\Application\Portal\Commands;

use App\Application\Contracts\Command;

final readonly class UnlinkPortalPartyScopeCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $userId,
        public string $scopeType,
        public int $scopeId,
    ) {}
}
