<?php

namespace Tests\Unit\Domain\Leave;

use App\Domain\Leave\Data\LeaveBalance;
use App\Domain\Leave\Data\LeaveDraft;
use App\Domain\Leave\Data\LeavePolicySnapshot;
use App\Domain\Leave\Data\LeavePolicyTerms;
use App\Domain\Leave\Data\LeaveRequester;
use App\Domain\Leave\Data\LeaveTypeSnapshot;
use App\Domain\Leave\Exceptions\LeaveRuleViolation;
use App\Domain\Leave\Services\LeaveApprovalRules;
use App\Domain\Leave\Services\LeaveDurationCalculator;
use App\Domain\Leave\Services\LeaveRequestRules;
use App\Domain\Leave\Services\LeaveTransitions;
use App\Domain\Leave\ValueObjects\DayBasis;
use App\Domain\Leave\ValueObjects\LeaveAudience;
use App\Domain\Leave\ValueObjects\LeaveStatus;
use App\Domain\Leave\ValueObjects\LeaveUnit;
use App\Domain\Leave\ValueObjects\RequesterKind;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LeaveDomainRulesTest extends TestCase
{
    /** Sunday → Thursday, the timetable default week. */
    private const WEEK = [1, 2, 3, 4, 5];

    #[Test]
    public function working_days_skip_the_weekend_and_holidays(): void
    {
        // 2026-11-01 is a Sunday; 06–07 (Fri, Sat) are the weekend.
        $this->assertSame(5, LeaveDurationCalculator::days('2026-11-01', '2026-11-07', DayBasis::WorkingDays, self::WEEK, []));
        $this->assertSame(4, LeaveDurationCalculator::days('2026-11-01', '2026-11-07', DayBasis::WorkingDays, self::WEEK, ['2026-11-03' => true]));
        $this->assertSame(7, LeaveDurationCalculator::days('2026-11-01', '2026-11-07', DayBasis::CalendarDays, self::WEEK, ['2026-11-03' => true]));
        $this->assertSame(0, LeaveDurationCalculator::days('2026-11-06', '2026-11-07', DayBasis::WorkingDays, self::WEEK, []));
        $this->assertSame(['2026-11-01', '2026-11-02', '2026-11-04'], LeaveDurationCalculator::workingDates('2026-11-01', '2026-11-04', self::WEEK, ['2026-11-03' => true]));
    }

    #[Test]
    public function invalid_or_reversed_ranges_count_nothing(): void
    {
        $this->assertSame([], LeaveDurationCalculator::dates('2026-11-05', '2026-11-01'));
        $this->assertSame([], LeaveDurationCalculator::dates('2026-02-30', '2026-03-01'));
        $this->assertFalse(LeaveDurationCalculator::isDate('2026-13-01'));
        $this->assertSame([], LeaveDurationCalculator::dates('2026-01-01', '2027-06-01'), 'longer than a year');
    }

    #[Test]
    public function the_lifecycle_allows_only_its_transitions(): void
    {
        $this->assertTrue(LeaveTransitions::allows(LeaveTransitions::SUBMIT, LeaveStatus::Draft));
        $this->assertTrue(LeaveTransitions::allows(LeaveTransitions::SUBMIT, LeaveStatus::InfoRequired));
        $this->assertFalse(LeaveTransitions::allows(LeaveTransitions::SUBMIT, LeaveStatus::Approved));
        $this->assertTrue(LeaveTransitions::allows(LeaveTransitions::DECIDE, LeaveStatus::Pending));
        $this->assertFalse(LeaveTransitions::allows(LeaveTransitions::DECIDE, LeaveStatus::Draft));
        $this->assertTrue(LeaveTransitions::allows(LeaveTransitions::CANCEL, LeaveStatus::Approved));
        $this->assertFalse(LeaveTransitions::allows(LeaveTransitions::WITHDRAW, LeaveStatus::Approved));
        $this->assertFalse(LeaveTransitions::allows(LeaveTransitions::EDIT, LeaveStatus::Pending));
        $this->assertFalse(LeaveTransitions::allows(LeaveTransitions::ATTACH, LeaveStatus::Rejected));

        $this->expectExceptionObject(LeaveRuleViolation::transition(LeaveTransitions::CANCEL, LeaveStatus::Pending->value));
        LeaveTransitions::assert(LeaveTransitions::CANCEL, LeaveStatus::Pending);
    }

    #[Test]
    public function quantity_follows_the_policy_and_the_year(): void
    {
        $type = $this->type(DayBasis::WorkingDays, maxPerRequest: 4);
        $draft = static fn (string $from, string $to): LeaveDraft => new LeaveDraft(1, $from, $to, null, 'سفر');

        $this->assertSame(4.0, LeaveRequestRules::quantity($type, RequesterKind::Teacher, $draft('2026-11-01', '2026-11-05'), '2026-09-01', '2027-06-30', self::WEEK, ['2026-11-03' => true]));
        $this->assertCode('outside_year', fn () => LeaveRequestRules::quantity($type, RequesterKind::Teacher, $draft('2027-07-01', '2027-07-02'), '2026-09-01', '2027-06-30', self::WEEK, []));
        $this->assertCode('duration_zero', fn () => LeaveRequestRules::quantity($type, RequesterKind::Teacher, $draft('2026-11-06', '2026-11-07'), '2026-09-01', '2027-06-30', self::WEEK, []));
        $this->assertCode('exceeds_max', fn () => LeaveRequestRules::quantity($type, RequesterKind::Teacher, $draft('2026-11-01', '2026-11-05'), '2026-09-01', '2027-06-30', self::WEEK, []));
        $this->assertCode('type_not_for_requester', fn () => LeaveRequestRules::quantity($this->type(DayBasis::WorkingDays, audience: LeaveAudience::Employees), RequesterKind::Teacher, $draft('2026-11-01', '2026-11-01'), '2026-09-01', '2027-06-30', self::WEEK, []));
        $this->assertCode('reason_invalid', fn () => LeaveRequestRules::quantity($type, RequesterKind::Teacher, new LeaveDraft(1, '2026-11-01', '2026-11-01', null, '   '), '2026-09-01', '2027-06-30', self::WEEK, []));
    }

    #[Test]
    public function hourly_leave_is_one_working_day_in_quarter_hours(): void
    {
        $type = $this->type(DayBasis::WorkingDays, unit: LeaveUnit::Hours);
        $hours = static fn (string $from, string $to, ?float $h): LeaveDraft => new LeaveDraft(1, $from, $to, $h, 'مراجعة');

        $this->assertSame(2.5, LeaveRequestRules::quantity($type, RequesterKind::Employee, $hours('2026-11-02', '2026-11-02', 2.5), '2026-09-01', '2027-06-30', self::WEEK, []));
        $this->assertCode('hours_one_day', fn () => LeaveRequestRules::quantity($type, RequesterKind::Employee, $hours('2026-11-02', '2026-11-03', 2), '2026-09-01', '2027-06-30', self::WEEK, []));
        $this->assertCode('hours_invalid', fn () => LeaveRequestRules::quantity($type, RequesterKind::Employee, $hours('2026-11-02', '2026-11-02', 2.1), '2026-09-01', '2027-06-30', self::WEEK, []));
        $this->assertCode('duration_zero', fn () => LeaveRequestRules::quantity($type, RequesterKind::Employee, $hours('2026-11-06', '2026-11-06', 2), '2026-09-01', '2027-06-30', self::WEEK, []));
    }

    #[Test]
    public function nobody_approves_their_own_leave_or_two_levels_of_one_request(): void
    {
        $requester = new LeaveRequester(RequesterKind::Teacher, 7, 70, 'معلم');

        LeaveApprovalRules::assertMayDecide($requester, 80, []);
        $this->assertCode('self_approval', fn () => LeaveApprovalRules::assertMayDecide($requester, 70, []));
        $this->assertCode('same_approver', fn () => LeaveApprovalRules::assertMayDecide($requester, 80, [80]));
        $this->assertCode('note_required', fn () => LeaveApprovalRules::note(true, '  '));
        $this->assertNull(LeaveApprovalRules::note(false, ''));
    }

    #[Test]
    public function the_balance_is_the_ledger_minus_what_open_requests_hold(): void
    {
        $balance = new LeaveBalance(entitled: 20, adjusted: 2, used: 5, restored: 1, reserved: 3);

        $this->assertSame(18.0, $balance->balance());
        $this->assertSame(15.0, $balance->available());
        $this->assertSame(4.0, $balance->toArray()['used']);
    }

    #[Test]
    public function policy_terms_and_audiences_are_checked(): void
    {
        $this->assertTrue(LeaveAudience::All->allows(RequesterKind::Employee));
        $this->assertFalse(LeaveAudience::Teachers->allows(RequesterKind::Employee));
        $this->assertCode('policy_levels_invalid', fn () => (new LeavePolicyTerms(DayBasis::WorkingDays, false, true, 4, null, null))->assertValid());
        $this->assertCode('policy_quantity_invalid', fn () => (new LeavePolicyTerms(DayBasis::WorkingDays, false, true, 1, 0.0, null))->assertValid());
        $this->assertCode('policy_quantity_invalid', fn () => (new LeavePolicyTerms(DayBasis::WorkingDays, false, true, 1, null, 2.3))->assertValid());
        (new LeavePolicyTerms(DayBasis::WorkingDays, false, true, 3, 10.5, 30))->assertValid();
    }

    private function type(DayBasis $basis, ?float $maxPerRequest = null, LeaveAudience $audience = LeaveAudience::All, LeaveUnit $unit = LeaveUnit::Days): LeaveTypeSnapshot
    {
        return new LeaveTypeSnapshot(1, 1, 'ANNUAL', 'اعتيادية', null, null, $audience, $unit, false, null, LeaveTypeSnapshot::ACTIVE,
            new LeavePolicySnapshot(1, 1, 1, $basis, false, true, 1, $maxPerRequest, 20, '2026-09-01', null));
    }

    private function assertCode(string $code, callable $call): void
    {
        try {
            $call();
            $this->fail("Expected leave.{$code}");
        } catch (LeaveRuleViolation $e) {
            $this->assertSame('leave.'.$code, $e->errorCode());
        }
    }
}
