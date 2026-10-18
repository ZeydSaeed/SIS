<?php

namespace App\Application\Leave\Support;

use App\Domain\Leave\Data\LeavePolicySnapshot;
use App\Domain\Leave\Data\LeaveRequestSnapshot;
use App\Domain\Leave\Exceptions\LeaveRuleViolation;
use App\Domain\Leave\Repositories\LeaveBalanceRepositoryInterface;
use App\Domain\Leave\ValueObjects\BalanceEntryType;

/**
 * Balance rules of a request whose policy deducts: on submit the balance must cover it (open requests of the same
 * person hold their share back), on final approval it is deducted once, on cancellation it is restored once.
 * Every check runs under the person's advisory lock inside the handler's transaction.
 */
final class LeaveBalanceGuard
{
    public function __construct(
        private readonly LeaveBalanceRepositoryInterface $balances,
    ) {}

    public function assertCovers(LeaveRequestSnapshot $request, LeavePolicySnapshot $policy, float $quantity): void
    {
        if (! $policy->deductsBalance) {
            return;
        }
        $this->balances->lockPerson($request->schoolId, $request->academicYearId, $request->leaveTypeId, $request->kind, $request->personId);
        $balance = $this->balances->balance($request->schoolId, $request->academicYearId, $request->leaveTypeId, $request->kind, $request->personId, $request->id);
        if ($balance->available() + 0.0001 < $quantity) {
            throw LeaveRuleViolation::of('balance_insufficient');
        }
    }

    /** @return float what was deducted (0 when the policy does not deduct) */
    public function deduct(LeaveRequestSnapshot $request, LeavePolicySnapshot $policy, int $actorUserId): float
    {
        if (! $policy->deductsBalance) {
            return 0.0;
        }
        $this->assertCovers($request, $policy, $request->quantity);
        if ($this->balances->hasRequestEntry($request->id, BalanceEntryType::Deduction)) {
            throw LeaveRuleViolation::of('already_deducted');
        }
        $this->balances->addEntry(
            $request->schoolId, $request->academicYearId, $request->leaveTypeId, $request->kind, $request->personId,
            BalanceEntryType::Deduction, -$request->quantity, $request->id, null, $actorUserId,
        );

        return $request->quantity;
    }

    /** @return float what was given back (0 when nothing had been deducted) */
    public function restore(LeaveRequestSnapshot $request, int $actorUserId, string $reason): float
    {
        $deducted = $this->balances->deductedFor($request->id);
        if ($deducted === null || $this->balances->hasRequestEntry($request->id, BalanceEntryType::Restoration)) {
            return 0.0;
        }
        $this->balances->lockPerson($request->schoolId, $request->academicYearId, $request->leaveTypeId, $request->kind, $request->personId);
        $this->balances->addEntry(
            $request->schoolId, $request->academicYearId, $request->leaveTypeId, $request->kind, $request->personId,
            BalanceEntryType::Restoration, $deducted, $request->id, $reason, $actorUserId,
        );

        return $deducted;
    }
}
