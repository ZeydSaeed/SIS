<?php

namespace App\Domain\Leave\Services;

use App\Domain\Leave\Exceptions\LeaveRuleViolation;
use App\Domain\Leave\ValueObjects\LeaveStatus;

/**
 * The request lifecycle:
 *
 *   draft ──submit──▶ pending ──approve (last level)──▶ approved ──cancel──▶ cancelled
 *     ▲                 │  ├──reject──▶ rejected
 *     │                 │  └──info──▶ info required ──resubmit──▶ pending
 *     └── edit ─────────┘ (draft / info required only)
 *   draft · pending · info required ──withdraw──▶ withdrawn
 */
final class LeaveTransitions
{
    public const EDIT = 'edit';

    public const SUBMIT = 'submit';

    public const DECIDE = 'decide';

    public const WITHDRAW = 'withdraw';

    public const CANCEL = 'cancel';

    public const ATTACH = 'attach';

    /** @var array<string, list<LeaveStatus>> */
    private const ALLOWED = [
        self::EDIT => [LeaveStatus::Draft, LeaveStatus::InfoRequired],
        self::SUBMIT => [LeaveStatus::Draft, LeaveStatus::InfoRequired],
        self::DECIDE => [LeaveStatus::Pending],
        self::WITHDRAW => [LeaveStatus::Draft, LeaveStatus::Pending, LeaveStatus::InfoRequired],
        self::CANCEL => [LeaveStatus::Approved],
        self::ATTACH => [LeaveStatus::Draft, LeaveStatus::Pending, LeaveStatus::InfoRequired, LeaveStatus::Approved],
    ];

    public static function allows(string $action, LeaveStatus $status): bool
    {
        return in_array($status, self::ALLOWED[$action] ?? [], true);
    }

    public static function assert(string $action, LeaveStatus $status): void
    {
        if (! self::allows($action, $status)) {
            throw LeaveRuleViolation::transition($action, $status->value);
        }
    }
}
