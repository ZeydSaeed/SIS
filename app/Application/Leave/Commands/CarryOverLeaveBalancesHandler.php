<?php

namespace App\Application\Leave\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Leave\Contracts\LeaveStaffDirectoryInterface;
use App\Application\Leave\Results\CarryOverLeaveBalancesResult;
use App\Application\Leave\Support\LeaveCalendar;
use App\Application\Leave\Support\LeaveRequestPreparer;
use App\Application\Leave\Support\LeaveTransaction;
use App\Domain\Leave\Data\LeavePolicyTerms;
use App\Domain\Leave\Data\LeaveRequester;
use App\Domain\Leave\Data\LeaveTypeSnapshot;
use App\Domain\Leave\Exceptions\LeaveRuleViolation;
use App\Domain\Leave\Repositories\LeaveBalanceRepositoryInterface;
use App\Domain\Leave\ValueObjects\BalanceEntryType;

/**
 * Carry-over is an ordinary, audited adjustment in the new year's ledger (the earlier year is never touched), worth the
 * person's available balance of the earlier year, floored to a quarter and capped. It runs once per person and source
 * year: a second run, or a person who already has the entry, adds nothing.
 */
final class CarryOverLeaveBalancesHandler implements CommandHandler
{
    public const COMMAND_NAME = 'Leave.CarryOverLeaveBalances';

    public function __construct(
        private readonly LeaveTransaction $tx,
        private readonly LeaveBalanceRepositoryInterface $balances,
        private readonly LeaveStaffDirectoryInterface $staff,
        private readonly LeaveRequestPreparer $prepare,
        private readonly LeaveCalendar $calendar,
    ) {}

    public function handle(Command $command): CarryOverLeaveBalancesResult
    {
        assert($command instanceof CarryOverLeaveBalancesCommand);

        return $this->tx->run(CarryOverLeaveBalancesResult::class, $command->idempotencyKey, self::COMMAND_NAME, function () use ($command): array {
            $type = $this->assertValid($command);
            $reason = 'ترحيل الرصيد من العام الدراسي #'.$command->fromYearId;

            $carried = 0;
            $total = 0.0;
            foreach ($this->staff->staff($command->schoolId, $command->toYearId, $type->audience) as $person) {
                $this->balances->lockPerson($command->schoolId, $command->toYearId, $type->id, $person->kind, $person->personId);
                if ($this->balances->hasAdjustmentWithReason($command->schoolId, $command->toYearId, $type->id, $person->kind, $person->personId, $reason)) {
                    continue;
                }
                $quantity = $this->quantity($command, $type->id, $person);
                if ($quantity <= 0) {
                    continue;
                }
                $this->balances->addEntry(
                    $command->schoolId, $command->toYearId, $type->id, $person->kind, $person->personId,
                    BalanceEntryType::Adjustment, $quantity, null, $reason, $command->actorUserId,
                );
                $carried++;
                $total += $quantity;
            }

            return [
                'data' => ['type_id' => $type->id, 'carried' => $carried, 'quantity' => round($total, 2)],
                'event' => LeaveTransaction::event('balance', $type->id, $command->schoolId, 'carried_over', $command->actorUserId, ['carried' => $carried, 'from_year_id' => $command->fromYearId, 'to_year_id' => $command->toYearId]),
            ];
        });
    }

    /** The source year must be earlier, the cap sensible, and the type active and balance-deducting. */
    private function assertValid(CarryOverLeaveBalancesCommand $command): LeaveTypeSnapshot
    {
        $earlier = $this->calendar->year($command->fromYearId)->startDate < $this->calendar->year($command->toYearId)->startDate;
        $capOk = $command->cap === null || ($command->cap > 0 && $command->cap <= LeavePolicyTerms::MAX_QUANTITY);
        $type = $this->prepare->type($command->schoolId, $command->typeId);
        if (! $earlier || ! $capOk || ! $type->isActive() || ! ($type->policy?->deductsBalance ?? false)) {
            throw LeaveRuleViolation::of('carry_over_invalid');
        }

        return $type;
    }

    /** The person's available balance of the source year, floored to a quarter and capped. */
    private function quantity(CarryOverLeaveBalancesCommand $command, int $typeId, LeaveRequester $person): float
    {
        $available = $this->balances->balance($command->schoolId, $command->fromYearId, $typeId, $person->kind, $person->personId)->available();

        return floor(min($available, $command->cap ?? $available) * 4) / 4;
    }
}
