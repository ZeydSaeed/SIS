<?php

namespace App\Domain\Attendance\ValueObjects;

/** attendance.staff_records.status — a teacher's work attendance (not lesson attendance). */
enum StaffAttendanceStatus: int
{
    case Present = 1;
    case Absent = 2;
    case Late = 3;
    case LeftEarly = 4;
    case ApprovedLeave = 5;
    case OfficialMission = 6;
    case ExcusedAbsence = 7;

    /** The teacher is not available to teach that day — their lessons need cover. */
    public function needsCover(): bool
    {
        return in_array($this, [self::Absent, self::ApprovedLeave, self::OfficialMission, self::ExcusedAbsence], true);
    }
}
