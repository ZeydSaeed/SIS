<?php

namespace App\Application\Teachers\Commands;

use App\Application\Contracts\Command;

/** «قفل» / «فتح القفل» of the selected teachers: whether they may sign in through the link. */
final readonly class SetTeachersAccessLockCommand implements Command
{
    /** @param  list<int>  $teacherIds */
    public function __construct(
        public int $schoolId,
        public array $teacherIds,
        public bool $locked,
        public ?string $idempotencyKey,
    ) {}
}
