<?php

namespace App\Application\Organization\Commands;

use App\Application\Contracts\Command;

final readonly class DeleteDepartmentsCommand implements Command
{
    public function __construct(
        public int $schoolId,
        /** @var list<int> */
        public array $departmentIds,
        public ?string $idempotencyKey,
    ) {}
}
