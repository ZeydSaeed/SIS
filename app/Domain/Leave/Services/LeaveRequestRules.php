<?php

namespace App\Domain\Leave\Services;

use App\Domain\Leave\Data\LeaveDraft;
use App\Domain\Leave\Data\LeaveTypeSnapshot;
use App\Domain\Leave\Exceptions\LeaveRuleViolation;
use App\Domain\Leave\ValueObjects\LeaveUnit;
use App\Domain\Leave\ValueObjects\RequesterKind;

/**
 * Field rules of one request against its type, current policy and academic year, then its quantity:
 * days of the range (working or calendar per the policy) or the hours of one working day.
 */
final class LeaveRequestRules
{
    public const MAX_REASON = 2000;

    public const MAX_HOURS = 12.0;

    /**
     * @param  list<int>  $workingWeekdays
     * @param  array<string, true>  $holidays
     * @return float the quantity (days or hours) the request takes
     */
    public static function quantity(
        LeaveTypeSnapshot $type,
        RequesterKind $kind,
        LeaveDraft $draft,
        string $yearStart,
        string $yearEnd,
        array $workingWeekdays,
        array $holidays,
    ): float {
        $policy = $type->policy;
        if (! $type->isActive() || $policy === null) {
            throw LeaveRuleViolation::of('type_inactive');
        }
        if (! $type->audience->allows($kind)) {
            throw LeaveRuleViolation::of('type_not_for_requester');
        }
        $reason = trim($draft->reason);
        if ($reason === '' || mb_strlen($reason) > self::MAX_REASON) {
            throw LeaveRuleViolation::of('reason_invalid');
        }
        if (! LeaveDurationCalculator::isDate($draft->dateFrom) || ! LeaveDurationCalculator::isDate($draft->dateTo)
            || $draft->dateTo < $draft->dateFrom || LeaveDurationCalculator::dates($draft->dateFrom, $draft->dateTo) === []) {
            throw LeaveRuleViolation::of('dates_invalid');
        }
        if ($draft->dateFrom < substr($yearStart, 0, 10) || $draft->dateTo > substr($yearEnd, 0, 10)) {
            throw LeaveRuleViolation::of('outside_year');
        }

        $quantity = $type->unit === LeaveUnit::Hours
            ? self::hours($draft, $workingWeekdays, $holidays)
            : (float) LeaveDurationCalculator::days($draft->dateFrom, $draft->dateTo, $policy->dayBasis, $workingWeekdays, $holidays);
        if ($quantity <= 0) {
            throw LeaveRuleViolation::of('duration_zero');
        }
        if ($policy->maxPerRequest !== null && $quantity > $policy->maxPerRequest) {
            throw LeaveRuleViolation::of('exceeds_max');
        }

        return $quantity;
    }

    /**
     * @param  list<int>  $workingWeekdays
     * @param  array<string, true>  $holidays
     */
    private static function hours(LeaveDraft $draft, array $workingWeekdays, array $holidays): float
    {
        if ($draft->dateFrom !== $draft->dateTo) {
            throw LeaveRuleViolation::of('hours_one_day');
        }
        if ($draft->hours === null || $draft->hours <= 0 || $draft->hours > self::MAX_HOURS || fmod($draft->hours * 4, 1.0) !== 0.0) {
            throw LeaveRuleViolation::of('hours_invalid');
        }

        return LeaveDurationCalculator::isWorkingDay($draft->dateFrom, $workingWeekdays, $holidays) ? $draft->hours : 0.0;
    }
}
