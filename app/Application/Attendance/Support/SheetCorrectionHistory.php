<?php

namespace App\Application\Attendance\Support;

use App\Domain\Attendance\Repositories\AttendanceSheetRepositoryInterface;
use App\Domain\Attendance\ValueObjects\SheetEventType;

/** Records an official correction on the sheet: version bump (stale editors conflict) + «تصحيح رسمي» history entry. */
final class SheetCorrectionHistory
{
    /**
     * @param  array<string, int>  $details
     */
    public static function record(?AttendanceSheetRepositoryInterface $sheets, int $schoolId, int $academicYearId, int $sessionId, ?int $actorUserId, string $reason, array $details): void
    {
        if ($sheets === null) {
            return;
        }
        $touch = $sheets->touchVersion($sessionId);
        $sheets->appendEvent($schoolId, $academicYearId, $sessionId, SheetEventType::Corrected, $touch['sheet_status'], $touch['sheet_status'], $touch['version'], $actorUserId, $reason, $details);
    }
}
