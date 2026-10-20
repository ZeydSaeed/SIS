<?php

namespace App\Application\Leave\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Leave\Results\CreateLeaveDelegationResult;
use App\Application\Leave\Support\LeaveTransaction;
use App\Domain\Leave\Exceptions\LeaveRuleViolation;
use App\Domain\Leave\Repositories\LeaveDelegationRepositoryInterface;

/**
 * A delegation is bounded in time (never open-ended, at most a year), goes to someone who works in the school, never
 * to oneself, and does not stack with an identical active one. The delegator's own right is checked by the HTTP layer
 * (the permission itself, not a delegated one) and again each time the delegate decides.
 */
final class CreateLeaveDelegationHandler implements CommandHandler
{
    public const COMMAND_NAME = 'Leave.CreateLeaveDelegation';

    private const MAX_DAYS = 366;

    public function __construct(
        private readonly LeaveTransaction $tx,
        private readonly LeaveDelegationRepositoryInterface $delegations,
    ) {}

    public function handle(Command $command): CreateLeaveDelegationResult
    {
        assert($command instanceof CreateLeaveDelegationCommand);

        return $this->tx->run(CreateLeaveDelegationResult::class, $command->idempotencyKey, self::COMMAND_NAME, function () use ($command): array {
            $from = \DateTimeImmutable::createFromFormat('!Y-m-d', $command->dateFrom);
            $to = \DateTimeImmutable::createFromFormat('!Y-m-d', $command->dateTo);
            if ($from === false || $to === false || $to < $from || $command->dateTo < $command->today
                || (int) $from->diff($to)->days > self::MAX_DAYS) {
                throw LeaveRuleViolation::of('delegation_dates_invalid');
            }
            if ($command->delegateUserId === $command->actorUserId
                || ! $this->delegations->hasRoleInSchool($command->schoolId, $command->delegateUserId)) {
                throw LeaveRuleViolation::of('delegate_invalid');
            }
            if ($this->delegations->overlapsActive($command->schoolId, $command->actorUserId, $command->delegateUserId, $command->dateFrom, $command->dateTo)) {
                throw LeaveRuleViolation::of('delegation_overlap');
            }
            $id = $this->delegations->create($command->schoolId, $command->actorUserId, $command->delegateUserId, $command->dateFrom, $command->dateTo, $command->actorUserId);

            return [
                'data' => ['delegation_id' => $id],
                'event' => LeaveTransaction::event('delegation', $id, $command->schoolId, 'created', $command->actorUserId, ['delegate_user_id' => $command->delegateUserId]),
            ];
        });
    }
}
