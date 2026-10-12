<?php

namespace App\Application\Attendance\Support;

use App\Application\Attendance\Contracts\AttendanceAnalyticsReadInterface;
use App\Application\Attendance\Contracts\LessonSourceInterface;
use App\Domain\Attendance\ValueObjects\AttendancePolicy;
use App\Domain\Attendance\ValueObjects\SheetStatus;

/**
 * Per-teacher inputs for the teacher alert rules on one date: lessons whose submission deadline passed without
 * a submitted sheet, lessons of teachers recorded unavailable that have no substitute, and returned sheets.
 */
final class TeacherAlertInputs
{
    public function __construct(
        private readonly LessonSourceInterface $lessons,
        private readonly AttendanceAnalyticsReadInterface $analytics,
    ) {}

    /**
     * @return list<array{teacher_id: int, missing: int, returned: int, absent_lessons: int}>
     */
    public function collect(int $schoolId, int $academicYearId, string $date, string $windowFrom, AttendancePolicy $policy, \DateTimeImmutable $now): array
    {
        $statuses = $this->analytics->sheetStatusesOn($schoolId, $date);
        $unavailable = array_flip($this->analytics->unavailableTeachers($schoolId, $date));
        $rows = [];
        foreach ($this->lessons->lessonsOn($schoolId, $academicYearId, $date) as $lesson) {
            $teacherId = $lesson->effectiveTeacherId();
            $rows[$teacherId] ??= ['teacher_id' => $teacherId, 'missing' => 0, 'returned' => 0, 'absent_lessons' => 0];
            $deadline = $policy->at($date, $lesson->endTime)->modify("+{$policy->submitDeadlineMinutes} minutes");
            $status = $statuses[$lesson->scheduleId] ?? null;
            $submitted = $status !== null && ! SheetStatus::from($status)->allowsTeacherEditing();
            if ($now > $deadline && ! $submitted) {
                $rows[$teacherId]['missing']++;
            }
            if ($lesson->substituteTeacherId === null && isset($unavailable[$lesson->teacherId])) {
                $rows[$teacherId]['absent_lessons']++;
            }
        }
        foreach ($this->analytics->returnedCounts($schoolId, $academicYearId, $windowFrom, $date) as $teacherId => $count) {
            $rows[$teacherId] ??= ['teacher_id' => $teacherId, 'missing' => 0, 'returned' => 0, 'absent_lessons' => 0];
            $rows[$teacherId]['returned'] = $count;
        }

        return array_values($rows);
    }
}
