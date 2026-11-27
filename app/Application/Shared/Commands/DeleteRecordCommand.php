<?php

namespace App\Application\Shared\Commands;

use App\Application\Contracts\Command;

/** «حذف» of one record of the current school (see SafeDeleter for what «safe» means). */
final readonly class DeleteRecordCommand implements Command
{
    public function __construct(
        public string $entity,
        public int $schoolId,
        public int $id,
        /** Several records at once, all or nothing (then `$id` is ignored). */
        public array $ids = [],
    ) {}
}
