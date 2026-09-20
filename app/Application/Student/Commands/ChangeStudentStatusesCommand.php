<?php

namespace App\Application\Student\Commands;

use App\Application\Contracts\Command;

final readonly class ChangeStudentStatusesCommand implements Command
{
    /**
     * @param  list<int>  $studentIds
     */
    public function __construct(
        public int $schoolId,
        public array $studentIds,
        public int $status,
        public ?string $idempotencyKey = null,
    ) {}
}
