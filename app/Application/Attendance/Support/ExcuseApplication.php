<?php

namespace App\Application\Attendance\Support;

use App\Domain\Attendance\Data\ExcuseSnapshot;
use App\Domain\Attendance\Repositories\AttendanceExcuseRepositoryInterface;
use App\Domain\Attendance\Repositories\AttendanceSheetRepositoryInterface;
use App\Domain\Attendance\Repositories\AttendanceWriteRepositoryInterface;
use App\Domain\Attendance\ValueObjects\SheetEventType;

/**
 * Applies an approved excuse as an official correction: «غائب» → «غياب بعذر» in range, one «تصحيح» history entry
 * and a version bump per touched sheet, daily summaries refreshed. Returns the changed rows (with their previous
 * status) for the audit trail.
 */
final class ExcuseApplication
{
    public function __construct(
        private readonly AttendanceExcuseRepositoryInterface $excuses,
        private readonly AttendanceSheetRepositoryInterface $sheets,
        private readonly AttendanceWriteRepositoryInterface $attendance,
    ) {}

    /**
     * @return list<array<string, int|string>>
     */
    public function apply(ExcuseSnapshot $excuse, int $actorUserId): array
    {
        $changed = $this->excuses->applyToAbsences($excuse, $actorUserId);
        $touched = [];
        foreach ($changed as $row) {
            if (isset($touched[$row['session_id']])) {
                continue;
            }
            $touched[$row['session_id']] = true;
            $touch = $this->sheets->touchVersion($row['session_id']);
            $this->sheets->appendEvent($excuse->schoolId, $excuse->academicYearId, $row['session_id'], SheetEventType::Corrected, $touch['sheet_status'], $touch['sheet_status'], $touch['version'], $actorUserId, 'excuse:'.$excuse->id, null);
            $this->attendance->refreshDailySectionSummary($row['section_id'], $excuse->schoolId, $excuse->academicYearId, $row['date']);
        }

        return $changed;
    }
}
