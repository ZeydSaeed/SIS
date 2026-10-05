<?php

namespace App\Application\Organization\Commands;

use App\Application\Contracts\Command;

final readonly class UpdateBranchCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $branchId,
        public string $name,
        public ?string $description,
        public ?string $idempotencyKey,
    ) {}
}
