<?php

namespace App\Application\Leave\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Leave\Results\WithdrawLeaveRequestResult;
use App\Application\Leave\Support\LeaveRequestPreparer;
use App\Application\Leave\Support\LeaveTransaction;
use App\Domain\Leave\Exceptions\LeaveRuleViolation;
use App\Domain\Leave\Repositories\LeaveRequestRepositoryInterface;
use App\Domain\Leave\Services\LeaveApprovalRules;
use App\Domain\Leave\Services\LeaveTransitions;
use App\Domain\Leave\ValueObjects\LeaveEventType;
use App\Domain\Leave\ValueObjects\LeaveStatus;

final class WithdrawLeaveRequestHandler implements CommandHandler
{
    public const COMMAND_NAME = 'Leave.WithdrawLeaveRequest';

    public function __construct(
        private readonly LeaveTransaction $tx,
        private readonly LeaveRequestRepositoryInterface $requests,
        private readonly LeaveRequestPreparer $prepare,
    ) {}

    public function handle(Command $command): WithdrawLeaveRequestResult
    {
        assert($command instanceof WithdrawLeaveRequestCommand);

        return $this->tx->run(WithdrawLeaveRequestResult::class, $command->idempotencyKey, self::COMMAND_NAME, function () use ($command): array {
            $request = $this->requests->lock($command->schoolId, $command->requestId) ?? throw LeaveRuleViolation::requestNotFound();
            $this->prepare->assertMayActFor($this->prepare->personOf($request), $command->actorUserId, $command->canManage);
            LeaveTransitions::assert(LeaveTransitions::WITHDRAW, $request->status);
            if ($command->expectedVersion !== $request->version) {
                throw LeaveRuleViolation::versionConflict();
            }
            $note = LeaveApprovalRules::note(false, $command->note);
            $this->requests->transition($request->id, $request->version, LeaveStatus::Withdrawn, $request->approvedLevels, null, null);
            $this->requests->addEvent($request->schoolId, $request->id, LeaveEventType::Withdrawn, $request->status, LeaveStatus::Withdrawn, null, $command->actorUserId, $note);

            return [
                'data' => ['request_id' => $request->id, 'status' => LeaveStatus::Withdrawn->value],
                'event' => LeaveTransaction::event('request', $request->id, $request->schoolId, 'withdrawn', $command->actorUserId),
            ];
        });
    }
}
