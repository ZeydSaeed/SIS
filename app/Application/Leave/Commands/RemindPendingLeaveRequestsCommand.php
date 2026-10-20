<?php

namespace App\Application\Leave\Commands;

use App\Application\Contracts\Command;

/**
 * The scheduled sweep of one school: approvers are reminded of requests pending for `$remindAfterDays`, the leave
 * managers are told of those still pending after `$escalateAfterDays`. `$now` is the moment of the sweep.
 */
final readonly class RemindPendingLeaveRequestsCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $remindAfterDays,
        public int $escalateAfterDays,
        public \DateTimeImmutable $now,
    ) {}
}
