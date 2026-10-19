<?php

namespace App\Application\Leave\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Leave\Results\DecideLeaveRequestResult;
use App\Application\Leave\Support\LeaveBalanceGuard;
use App\Application\Leave\Support\LeaveNotifier;
use App\Application\Leave\Support\LeaveRequestPreparer;
use App\Application\Leave\Support\LeaveTransaction;
use App\Domain\Leave\Data\LeaveRequester;
use App\Domain\Leave\Data\LeaveRequestSnapshot;
use App\Domain\Leave\Exceptions\LeaveRuleViolation;
use App\Domain\Leave\Repositories\LeaveRequestRepositoryInterface;
use App\Domain\Leave\Repositories\LeaveTypeRepositoryInterface;
use App\Domain\Leave\Services\LeaveApprovalRules;
use App\Domain\Leave\Services\LeaveTransitions;
use App\Domain\Leave\ValueObjects\LeaveDecision;
use App\Domain\Leave\ValueObjects\LeaveEventType;
use App\Domain\Leave\ValueObjects\LeaveNotificationKind;
use App\Domain\Leave\ValueObjects\LeaveStatus;

/**
 * The request row is locked, its version must be the one the approver saw, the approver may not be the person on
 * leave nor someone who already approved a level, and the final approval deducts the balance exactly once (the
 * ledger's unique (request, deduction) backs this up against any retry).
 */
final class DecideLeaveRequestHandler implements CommandHandler
{
    public const COMMAND_NAME = 'Leave.DecideLeaveRequest';

    public function __construct(
        private readonly LeaveTransaction $tx,
        private readonly LeaveRequestRepositoryInterface $requests,
        private readonly LeaveTypeRepositoryInterface $types,
        private readonly LeaveRequestPreparer $prepare,
        private readonly LeaveBalanceGuard $balance,
        private readonly LeaveNotifier $notifier,
    ) {}

    public function handle(Command $command): DecideLeaveRequestResult
    {
        assert($command instanceof DecideLeaveRequestCommand);

        return $this->tx->run(DecideLeaveRequestResult::class, $command->idempotencyKey, self::COMMAND_NAME, function () use ($command): array {
            $request = $this->requests->lock($command->schoolId, $command->requestId) ?? throw LeaveRuleViolation::requestNotFound();
            LeaveTransitions::assert(LeaveTransitions::DECIDE, $request->status);
            if ($command->expectedVersion !== $request->version) {
                throw LeaveRuleViolation::versionConflict();
            }
            $requester = $this->prepare->personOf($request);
            $approvers = $this->requests->currentApprovers($request->id);
            LeaveApprovalRules::assertMayDecide($requester, $command->actorUserId, $approvers);
            $note = LeaveApprovalRules::note($command->decision->requiresNote(), $command->note);

            [$to, $event, $levels, $deducted] = match ($command->decision) {
                LeaveDecision::Approve => $this->approve($request, $command->actorUserId),
                LeaveDecision::Reject => [LeaveStatus::Rejected, LeaveEventType::Rejected, $request->approvedLevels, 0.0],
                LeaveDecision::RequestInfo => [LeaveStatus::InfoRequired, LeaveEventType::InfoRequested, 0, 0.0],
            };
            $final = $to !== LeaveStatus::Pending;
            $this->requests->transition($request->id, $request->version, $to, $levels, $final ? $command->actorUserId : null, $final ? $note : null);
            $this->requests->addEvent($request->schoolId, $request->id, $event, $request->status, $to, $levels, $command->actorUserId, $note, ['deducted' => $deducted]);

            $this->tell($request, $requester, $to, $levels, $command->actorUserId, $approvers);

            return [
                'data' => ['request_id' => $request->id, 'status' => $to->value, 'approved_levels' => $levels, 'deducted' => $deducted],
                'event' => LeaveTransaction::event('request', $request->id, $request->schoolId, $command->decision->value, $command->actorUserId, ['status' => $to->value, 'level' => $levels]),
            ];
        });
    }

    /** @param  list<int>  $approvers  users who already approved a level of this request */
    private function tell(LeaveRequestSnapshot $request, LeaveRequester $requester, LeaveStatus $to, int $levels, int $actor, array $approvers): void
    {
        if ($to === LeaveStatus::Pending) {
            // A level passed, the next one waits: the requester is told, the next approvers are asked.
            $this->notifier->outcome($request, $requester, LeaveNotificationKind::LevelApproved, $actor);
            $this->notifier->awaitingApproval($request, $requester, $actor, [...$approvers, $actor]);

            return;
        }
        $kind = match ($to) {
            LeaveStatus::Approved => LeaveNotificationKind::Approved,
            LeaveStatus::Rejected => LeaveNotificationKind::Rejected,
            default => LeaveNotificationKind::InfoRequested,
        };
        $this->notifier->outcome($request, $requester, $kind, $actor);
    }

    /** @return array{0: LeaveStatus, 1: LeaveEventType, 2: int, 3: float} */
    private function approve(LeaveRequestSnapshot $request, int $actorUserId): array
    {
        $level = $request->approvedLevels + 1;
        if ($level < $request->approvalLevels) {
            return [LeaveStatus::Pending, LeaveEventType::LevelApproved, $level, 0.0];
        }
        // Decided under the policy version frozen at submission, not whatever is current now.
        $policy = $this->types->policy($request->schoolId, $request->policyId) ?? throw LeaveRuleViolation::of('policy_missing');

        return [LeaveStatus::Approved, LeaveEventType::Approved, $level, $this->balance->deduct($request, $policy, $actorUserId)];
    }
}
