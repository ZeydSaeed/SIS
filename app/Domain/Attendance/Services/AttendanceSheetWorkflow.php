<?php

namespace App\Domain\Attendance\Services;

use App\Domain\Attendance\Exceptions\AttendanceRuleViolation;
use App\Domain\Attendance\ValueObjects\SheetAction;
use App\Domain\Attendance\ValueObjects\SheetStatus;

/**
 * The only place sheet transitions are defined. Handlers ask for the next status; nothing writes a
 * sheet_status value that did not come from here.
 */
final class AttendanceSheetWorkflow
{
    /** @var array<string, array<int, SheetStatus>> action → (from → to) */
    private const TRANSITIONS = [
        'submit' => [1 => SheetStatus::Submitted, 5 => SheetStatus::Submitted],
        'start_review' => [2 => SheetStatus::UnderReview],
        'approve' => [2 => SheetStatus::Approved, 3 => SheetStatus::Approved],
        'return' => [2 => SheetStatus::Returned, 3 => SheetStatus::Returned],
        'reject' => [2 => SheetStatus::Rejected, 3 => SheetStatus::Rejected],
    ];

    public static function next(SheetStatus $from, SheetAction $action): SheetStatus
    {
        $to = self::TRANSITIONS[$action->value][$from->value] ?? null;
        if ($to === null) {
            throw AttendanceRuleViolation::invalidTransition($from->name, $action->value);
        }

        return $to;
    }

    public static function allows(SheetStatus $from, SheetAction $action): bool
    {
        return isset(self::TRANSITIONS[$action->value][$from->value]);
    }

    /**
     * @return list<string> actions available from a status (for UI hints; the server re-checks).
     */
    public static function availableActions(SheetStatus $from): array
    {
        $out = [];
        foreach (SheetAction::cases() as $action) {
            if (self::allows($from, $action)) {
                $out[] = $action->value;
            }
        }

        return $out;
    }

    /** Marks may change only while the sheet is a draft or returned for correction. */
    public static function assertEditable(int $sheetStatus, int $sessionId): void
    {
        if (! SheetStatus::from($sheetStatus)->allowsTeacherEditing()) {
            throw AttendanceRuleViolation::notEditable($sessionId);
        }
    }

    public static function assertReason(SheetAction $action, ?string $reason): ?string
    {
        $reason = $reason !== null ? trim($reason) : null;
        if ($action->requiresReason() && ($reason === null || $reason === '')) {
            throw AttendanceRuleViolation::reasonRequired();
        }

        return $reason === '' ? null : $reason;
    }
}
