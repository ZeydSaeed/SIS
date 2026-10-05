<?php

namespace App\Application\Organization\Commands;

use App\Application\Contracts\Command;

final readonly class UpdateSchoolCommand implements Command
{
    /**
     * @param  array{directorate_id?: int, name?: string, address?: ?string, phone?: ?string, email?: ?string}  $fields
     */
    public function __construct(
        public int $schoolId,
        public array $fields,
        public ?string $idempotencyKey,
    ) {}
}
