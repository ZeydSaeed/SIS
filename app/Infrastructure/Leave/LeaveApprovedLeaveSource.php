<?php

namespace App\Infrastructure\Leave;

use App\Application\Attendance\Contracts\ApprovedLeaveSourceInterface;
use App\Application\Leave\Contracts\LeaveReadRepositoryInterface;
use App\Database\SchemaHelper;
use App\Domain\Leave\ValueObjects\RequesterKind;

/** Attendance's view of approved teacher leave, read through the leave module's read model (no leave details). */
final class LeaveApprovedLeaveSource implements ApprovedLeaveSourceInterface
{
    public function __construct(
        private readonly LeaveReadRepositoryInterface $read,
    ) {}

    public function teachersOnApprovedLeave(int $schoolId, string $date): array
    {
        // The leave schema is PostgreSQL-only (like the attendance lifecycle tables): elsewhere nobody is on leave.
        if (! SchemaHelper::isPostgreSql()) {
            return [];
        }
        $out = [];
        foreach ($this->read->awayOn($schoolId, $date, null) as $row) {
            if ((int) $row['requester_kind'] === RequesterKind::Teacher->value) {
                $out[(int) $row['person_id']] = (int) $row['request_no'];
            }
        }

        return $out;
    }
}
