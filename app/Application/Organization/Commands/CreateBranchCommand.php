<?php

namespace App\Application\Organization\Commands;

use App\Application\Contracts\Command;

final readonly class CreateBranchCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public string $name,
        public ?string $description,
        public ?string $idempotencyKey,
        /** 1 نشط · 2 غير نشط · 3 مؤرشف */
        public int $status = 1,
    ) {}
}
