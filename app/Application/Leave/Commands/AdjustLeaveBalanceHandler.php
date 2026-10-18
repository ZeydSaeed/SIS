<?php

namespace App\Application\Leave\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Leave\Results\AdjustLeaveBalanceResult;
use App\Application\Leave\Support\LeaveRequestPreparer;
use App\Application\Leave\Support\LeaveTransaction;
use App\Domain\Leave\Data\LeavePolicyTerms;
use App\Domain\Leave\Exceptions\LeaveRuleViolation;
use App\Domain\Leave\Repositories\LeaveBalanceRepositoryInterface;
use App\Domain\Leave\Services\LeaveApprovalRules;
use App\Domain\Leave\ValueObjects\BalanceEntryType;

/**
 * A negative adjustment may not take the balance below what open requests already hold back — the person would
 * otherwise owe days nobody granted.
 */
final class AdjustLeaveBalanceHandler implements CommandHandler
{
    public const COMMAND_NAME = 'Leave.AdjustLeaveBalance';

    public function __construct(
        private readonly LeaveTransaction $tx,
        private readonly LeaveBalanceRepositoryInterface $balances,
        private readonly LeaveRequestPreparer $prepare,
    ) {}

    public function handle(Command $command): AdjustLeaveBalanceResult
    {
        assert($command instanceof AdjustLeaveBalanceCommand);
        $quantity = round($command->quantity, 2);
        if ($quantity === 0.0 || abs($quantity) > LeavePolicyTerms::MAX_QUANTITY || fmod(abs($quantity) * 4, 1.0) !== 0.0) {
            throw LeaveRuleViolation::of('adjustment_invalid');
        }
        $reason = (string) LeaveApprovalRules::note(true, $command->reason);

        return $this->tx->run(AdjustLeaveBalanceResult::class, $command->idempotencyKey, self::COMMAND_NAME, function () use ($command, $quantity, $reason): array {
            $this->prepare->requester($command->schoolId, $command->academicYearId, $command->kind, $command->personId);
            $type = $this->prepare->type($command->schoolId, $command->typeId);
            $this->balances->lockPerson($command->schoolId, $command->academicYearId, $type->id, $command->kind, $command->personId);
            $balance = $this->balances->balance($command->schoolId, $command->academicYearId, $type->id, $command->kind, $command->personId);
            if ($balance->available() + $quantity < 0) {
                throw LeaveRuleViolation::of('balance_negative');
            }
            $id = $this->balances->addEntry(
                $command->schoolId, $command->academicYearId, $type->id, $command->kind, $command->personId,
                BalanceEntryType::Adjustment, $quantity, null, $reason, $command->actorUserId,
            );

            return [
                'data' => ['entry_id' => $id, 'balance' => round($balance->balance() + $quantity, 2)],
                'event' => LeaveTransaction::event('balance', $id, $command->schoolId, 'adjusted', $command->actorUserId, ['type_id' => $type->id, 'quantity' => $quantity]),
            ];
        });
    }
}
