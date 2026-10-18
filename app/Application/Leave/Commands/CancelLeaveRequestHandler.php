<?php

namespace App\Application\Leave\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Leave\Results\CancelLeaveRequestResult;
use App\Application\Leave\Support\LeaveBalanceGuard;
use App\Application\Leave\Support\LeaveTransaction;
use App\Domain\Leave\Exceptions\LeaveRuleViolation;
use App\Domain\Leave\Repositories\LeaveRequestRepositoryInterface;
use App\Domain\Leave\Services\LeaveApprovalRules;
use App\Domain\Leave\Services\LeaveTransitions;
use App\Domain\Leave\ValueObjects\LeaveEventType;
use App\Domain\Leave\ValueObjects\LeaveStatus;

final class CancelLeaveRequestHandler implements CommandHandler
{
    public const COMMAND_NAME = 'Leave.CancelLeaveRequest';

    public function __construct(
        private readonly LeaveTransaction $tx,
        private readonly LeaveRequestRepositoryInterface $requests,
        private readonly LeaveBalanceGuard $balance,
    ) {}

    public function handle(Command $command): CancelLeaveRequestResult
    {
        assert($command instanceof CancelLeaveRequestCommand);

        return $this->tx->run(CancelLeaveRequestResult::class, $command->idempotencyKey, self::COMMAND_NAME, function () use ($command): array {
            $request = $this->requests->lock($command->schoolId, $command->requestId) ?? throw LeaveRuleViolation::requestNotFound();
            LeaveTransitions::assert(LeaveTransitions::CANCEL, $request->status);
            if ($command->expectedVersion !== $request->version) {
                throw LeaveRuleViolation::versionConflict();
            }
            $note = (string) LeaveApprovalRules::note(true, $command->note);
            $restored = $this->balance->restore($request, $command->actorUserId, $note);
            $this->requests->transition($request->id, $request->version, LeaveStatus::Cancelled, $request->approvedLevels, $command->actorUserId, $note);
            $this->requests->addEvent($request->schoolId, $request->id, LeaveEventType::Cancelled, $request->status, LeaveStatus::Cancelled, null, $command->actorUserId, $note, ['restored' => $restored]);

            return [
                'data' => ['request_id' => $request->id, 'status' => LeaveStatus::Cancelled->value, 'restored' => $restored],
                'event' => LeaveTransaction::event('request', $request->id, $request->schoolId, 'cancelled', $command->actorUserId, ['restored' => $restored]),
            ];
        });
    }
}
