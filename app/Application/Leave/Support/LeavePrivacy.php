<?php

namespace App\Application\Leave\Support;

/**
 * Sensitive leave (e.g. medical) of someone else, for a viewer without «عرض الإجازات الحساسة»: the dates and the
 * status stay visible (the school must know who is away), the type name, the reason and the attachments do not.
 * Applied on the server before anything leaves it — lists, details, calendar and reports alike.
 */
final class LeavePrivacy
{
    /**
     * @param  array<string, mixed>  $row  needs is_sensitive, requester_user_id, created_by
     * @return array<string, mixed>
     */
    public static function mask(array $row, LeaveViewer $viewer): array
    {
        $masked = self::hides($row, $viewer);
        $row['masked'] = $masked;
        if ($masked) {
            $row['type_name'] = null;
            $row['type_code'] = null;
            $row['reason'] = null;
            $row['decision_note'] = null;
        }
        unset($row['requester_user_id']);

        return $row;
    }

    /** @param  array<string, mixed>  $row */
    public static function hides(array $row, LeaveViewer $viewer): bool
    {
        if (! ($row['is_sensitive'] ?? false) || $viewer->seesSensitive) {
            return false;
        }
        $own = (int) ($row['requester_user_id'] ?? 0) === $viewer->userId || (int) ($row['created_by'] ?? 0) === $viewer->userId;

        return ! $own;
    }
}
