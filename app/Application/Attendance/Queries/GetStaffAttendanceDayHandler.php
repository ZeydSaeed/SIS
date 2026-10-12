<?php

namespace App\Application\Attendance\Queries;

use App\Application\Attendance\Contracts\AttendanceClockInterface;
use App\Application\Attendance\Contracts\AttendanceWorkspaceReadInterface;
use App\Application\Attendance\Contracts\LessonSourceInterface;
use App\Application\Attendance\DTOs\AttendanceViewDTO;
use App\Application\Attendance\Support\AttendancePolicyResolver;
use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Domain\Attendance\Data\LessonSlot;
use App\Domain\Attendance\ValueObjects\StaffAttendanceStatus;

/**
 * Teacher work attendance + the lessons that need cover: lessons of teachers recorded unavailable that day with
 * no substitute assigned in the timetable. The timetable is never changed here — cover is assigned through the
 * timetable's substitution (schedule exception) workflow by an authorised user.
 */
final class GetStaffAttendanceDayHandler implements QueryHandler
{
    public function __construct(
        private readonly AttendanceWorkspaceReadInterface $read,
        private readonly LessonSourceInterface $lessons,
        private readonly AttendancePolicyResolver $policies,
        private readonly AttendanceClockInterface $clock,
    ) {}

    public function handle(Query $query): AttendanceViewDTO
    {
        assert($query instanceof GetStaffAttendanceDayQuery);

        $policy = $this->policies->current($query->schoolId, $query->academicYearId);
        $today = $policy->localDate($this->clock->now());
        $date = $query->date !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $query->date) === 1 ? $query->date : $today;
        $base = ['date' => $date, 'today' => $today, 'staff_day_start' => $policy->staffDayStart, 'grace_minutes' => $policy->staffLateGraceMinutes];

        if (! $query->canManage) {
            $from = (new \DateTimeImmutable($today))->modify('-30 days')->format('Y-m-d');
            $history = $query->selfTeacherId !== null ? $this->read->staffHistory($query->schoolId, $query->selfTeacherId, $from, $today) : [];

            return new AttendanceViewDTO($base + ['history' => $history, 'rows' => [], 'cover' => []]);
        }

        $rows = $this->read->staffDay($query->schoolId, $query->academicYearId, $date);
        $lessons = $this->lessons->lessonsOn($query->schoolId, $query->academicYearId, $date);
        $perTeacher = [];
        foreach ($lessons as $lesson) {
            $perTeacher[$lesson->effectiveTeacherId()] = ($perTeacher[$lesson->effectiveTeacherId()] ?? 0) + 1;
        }
        $unavailable = [];
        foreach ($rows as $i => $row) {
            $rows[$i]['lessons_today'] = $perTeacher[(int) $row['teacher_id']] ?? 0;
            $status = isset($row['status']) ? StaffAttendanceStatus::tryFrom((int) $row['status']) : null;
            if ($status !== null && $status->needsCover()) {
                $unavailable[(int) $row['teacher_id']] = true;
            }
        }
        $cover = array_values(array_map(
            static fn (LessonSlot $l): array => $l->toArray(),
            array_filter($lessons, static fn (LessonSlot $l): bool => $l->substituteTeacherId === null && isset($unavailable[$l->teacherId])),
        ));

        return new AttendanceViewDTO($base + ['rows' => $rows, 'cover' => $cover, 'history' => []]);
    }
}
