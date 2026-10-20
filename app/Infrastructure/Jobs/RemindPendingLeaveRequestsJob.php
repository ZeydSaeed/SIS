<?php

namespace App\Infrastructure\Jobs;

use App\Application\Leave\Commands\RemindPendingLeaveRequestsCommand;
use App\Application\Leave\Commands\RemindPendingLeaveRequestsHandler;
use App\Security\Context\SchoolContextScope;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Daily, in the background (never in an HTTP request): each active school under its own tenant context — approvers
 * are reminded of leave requests waiting too long and the leave managers are told of those waiting longer still.
 * Notifications only; running it twice a day tells nobody twice.
 */
final class RemindPendingLeaveRequestsJob implements ShouldQueue
{
    use Queueable;

    public function handle(RemindPendingLeaveRequestsHandler $handler, SchoolContextScope $scope): void
    {
        $remind = (int) config('sis.leave.remind_after_days', 2);
        $escalate = max($remind, (int) config('sis.leave.escalate_after_days', 5));
        $now = new \DateTimeImmutable;

        foreach (DB::table('organization.schools')->where('status', 1)->pluck('id') as $schoolId) {
            try {
                $scope->run((int) $schoolId, fn () => $handler->handle(new RemindPendingLeaveRequestsCommand((int) $schoolId, $remind, $escalate, $now)));
            } catch (\Throwable $e) {
                // One school's failure never stops the others.
                Log::error('leave.reminders_failed', ['school_id' => $schoolId, 'error' => $e->getMessage()]);
            }
        }
    }
}
