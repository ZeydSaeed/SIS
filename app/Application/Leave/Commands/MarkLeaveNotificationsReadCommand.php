<?php

namespace App\Application\Leave\Commands;

use App\Application\Contracts\Command;

/** Marks the user's own notifications read (the given ids, or every unread one when `ids` is null). */
final readonly class MarkLeaveNotificationsReadCommand implements Command
{
    /** @param  list<int>|null  $ids */
    public function __construct(
        public int $schoolId,
        public int $userId,
        public ?array $ids,
        public ?string $idempotencyKey,
    ) {}
}
