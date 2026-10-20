<?php

namespace App\Application\Leave\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\UnitOfWork;
use App\Application\Leave\Contracts\LeaveReadRepositoryInterface;
use App\Application\Leave\Results\RemindPendingLeaveRequestsResult;
use App\Application\Leave\Support\LeaveNotifier;
use App\Application\Leave\Support\LeaveRequestPreparer;
use App\Domain\Leave\Repositories\LeaveRequestRepositoryInterface;

/**
 * Reminders change nothing but notifications. Each approver gets one reminder per round of a request (the round starts
 * at its submission; a returned and re-submitted request starts a new one), each manager one escalation. Running the
 * sweep again the same day tells nobody twice.
 */
final class RemindPendingLeaveRequestsHandler implements CommandHandler
{
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly LeaveReadRepositoryInterface $read,
        private readonly LeaveRequestRepositoryInterface $requests,
        private readonly LeaveRequestPreparer $prepare,
        private readonly LeaveNotifier $notifier,
    ) {}

    public function handle(Command $command): RemindPendingLeaveRequestsResult
    {
        assert($command instanceof RemindPendingLeaveRequestsCommand);
        $remindBefore = $command->now->modify("-{$command->remindAfterDays} days")->format('Y-m-d H:i:sP');
        $escalateBefore = $command->now->modify("-{$command->escalateAfterDays} days");
        $pending = $this->read->pendingSubmittedBefore($command->schoolId, $remindBefore);

        $reminded = 0;
        $escalated = 0;
        foreach ($pending as $row) {
            $this->unitOfWork->transaction(function () use ($command, $row, $escalateBefore, &$reminded, &$escalated): void {
                $request = $this->requests->find($command->schoolId, $row['id']);
                if ($request === null) {
                    return;
                }
                $requester = $this->prepare->personOf($request);
                $reminded += $this->notifier->reminder($request, $requester, $row['submitted_at'], $this->requests->currentApprovers($request->id));
                if (new \DateTimeImmutable($row['submitted_at']) <= $escalateBefore) {
                    $escalated += $this->notifier->escalation($request, $requester, $row['submitted_at']);
                }
            });
        }

        return RemindPendingLeaveRequestsResult::success(['reminded' => $reminded, 'escalated' => $escalated]);
    }
}
