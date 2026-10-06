<?php

namespace App\Application\Teachers\Commands;

use App\Application\Contracts\Command;

/** نشط / غير نشط for one or more selected teachers of the school. */
final readonly class ChangeTeachersStatusCommand implements Command
{
    /** @param  list<int>  $teacherIds */
    public function __construct(
        public int $schoolId,
        public array $teacherIds,
        public int $status,
        public ?string $idempotencyKey,
    ) {}
}
