<?php

namespace App\Domain\Leave\Services;

use App\Domain\Leave\Data\LeaveRequester;
use App\Domain\Leave\Exceptions\LeaveRuleViolation;

/**
 * Who may decide: never the person on leave (self-approval), and with several approval levels, each level by a
 * different approver — one person cannot pass a request through two levels.
 */
final class LeaveApprovalRules
{
    /**
     * @param  list<int>  $previousApprovers  users who already approved a level of this request
     */
    public static function assertMayDecide(LeaveRequester $requester, int $actorUserId, array $previousApprovers): void
    {
        if ($requester->isUser($actorUserId)) {
            throw LeaveRuleViolation::of('self_approval');
        }
        if (in_array($actorUserId, $previousApprovers, true)) {
            throw LeaveRuleViolation::of('same_approver');
        }
    }

    public static function note(bool $required, ?string $note): ?string
    {
        $note = $note === null ? '' : trim($note);
        if ($required && $note === '') {
            throw LeaveRuleViolation::of('note_required');
        }
        if (mb_strlen($note) > LeaveRequestRules::MAX_REASON) {
            throw LeaveRuleViolation::of('note_too_long');
        }

        return $note === '' ? null : $note;
    }
}
