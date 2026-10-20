<?php

namespace App\Application\Leave\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Leave\Results\RevokeLeaveDelegationResult;
use App\Application\Leave\Support\LeaveTransaction;
use App\Domain\Leave\Exceptions\LeaveRuleViolation;
use App\Domain\Leave\Repositories\LeaveDelegationRepositoryInterface;

final class RevokeLeaveDelegationHandler implements CommandHandler
{
    public const COMMAND_NAME = 'Leave.RevokeLeaveDelegation';

    public function __construct(
        private readonly LeaveTransaction $tx,
        private readonly LeaveDelegationRepositoryInterface $delegations,
    ) {}

    public function handle(Command $command): RevokeLeaveDelegationResult
    {
        assert($command instanceof RevokeLeaveDelegationCommand);

        return $this->tx->run(RevokeLeaveDelegationResult::class, $command->idempotencyKey, self::COMMAND_NAME, function () use ($command): array {
            $row = $this->delegations->find($command->schoolId, $command->delegationId) ?? throw LeaveRuleViolation::of('delegation_missing');
            if (! $command->asManager && $row['delegator_user_id'] !== $command->actorUserId) {
                // Another person's delegation is indistinguishable from a missing one.
                throw LeaveRuleViolation::of('delegation_missing');
            }
            if (! $this->delegations->revoke($command->schoolId, $command->delegationId, $command->actorUserId)) {
                throw LeaveRuleViolation::of('delegation_not_active');
            }

            return [
                'data' => ['delegation_id' => $command->delegationId],
                'event' => LeaveTransaction::event('delegation', $command->delegationId, $command->schoolId, 'revoked', $command->actorUserId),
            ];
        });
    }
}
