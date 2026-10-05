<?php

namespace App\Application\Organization\Commands;

use App\Application\Contracts\Command;

final readonly class DeleteBranchCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $branchId,
        public ?string $idempotencyKey,
    ) {}
}
