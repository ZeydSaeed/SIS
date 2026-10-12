<?php

namespace App\Application\Attendance\Queries;

use App\Application\Attendance\Contracts\AttendanceClockInterface;
use App\Application\Attendance\Contracts\AttendanceWorkspaceReadInterface;
use App\Application\Attendance\Contracts\LessonSourceInterface;
use App\Application\Attendance\Contracts\TeacherDirectoryInterface;
use App\Application\Attendance\DTOs\AttendanceViewDTO;
use App\Application\Attendance\Support\AttendancePolicyResolver;
use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Domain\Attendance\Data\LessonSlot;
use App\Domain\Attendance\Repositories\AttendanceSheetRepositoryInterface;
use App\Domain\Attendance\ValueObjects\AttendancePolicy;
use App\Domain\Attendance\ValueObjects\CalendarDate;

/**
 * The date is the school-local date computed on the server (the browser clock is never trusted). A reviewer may
 * look at any teacher's day; a teacher sees only their own.
 */
final class GetTeacherDayHandler implements QueryHandler
{
    public function __construct(
        private readonly LessonSourceInterface $lessons,
        private readonly AttendanceWorkspaceReadInterface $read,
        private readonly AttendanceSheetRepositoryInterface $sheets,
        private readonly TeacherDirectoryInterface $teachers,
        private readonly AttendancePolicyResolver $policies,
        private readonly AttendanceClockInterface $clock,
    ) {}

    public function handle(Query $query): AttendanceViewDTO
    {
        assert($query instanceof GetTeacherDayQuery);

        $policy = $this->policies->current($query->schoolId, $query->academicYearId);
        $now = $this->clock->now();
        $today = $policy->localDate($now);
        $date = CalendarDate::valid($query->date) ? (string) $query->date : $today;
        $teacherId = $query->actor->isReviewer && $query->teacherId !== null ? $query->teacherId : $query->actor->teacherId;

        $base = [
            'date' => $date,
            'today' => $today,
            'now' => $now->setTimezone($policy->zone())->format('H:i'),
            'weekday' => (int) (new \DateTimeImmutable($date))->format('w') + 1,
            'timezone' => $policy->timezone,
            'holiday' => $this->sheets->isHoliday($query->schoolId, $query->academicYearId, $date),
        ];
        if ($teacherId === null) {
            return new AttendanceViewDTO($base + ['teacher' => null, 'lessons' => [], 'notices' => []]);
        }

        $lessons = $this->lessons->lessonsOn($query->schoolId, $query->academicYearId, $date, $teacherId);
        $sessions = $this->read->sessionsForLessons($query->schoolId, $date, array_map(static fn (LessonSlot $l): int => $l->scheduleId, $lessons));

        return new AttendanceViewDTO($base + [
            'teacher' => $this->teachers->teacher($teacherId),
            'lessons' => array_map(fn (LessonSlot $l): array => $this->lessonRow($l, $sessions[$l->scheduleId] ?? null, $policy, $now), $lessons),
            'notices' => $this->read->teacherNotices($query->schoolId, $query->academicYearId, $teacherId, 10),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $session
     * @return array<string, mixed>
     */
    private function lessonRow(LessonSlot $lesson, ?array $session, AttendancePolicy $policy, \DateTimeImmutable $now): array
    {
        $start = $policy->at($lesson->date, $lesson->startTime);
        $end = $policy->at($lesson->date, $lesson->endTime);

        return $lesson->toArray() + [
            'window' => $policy->windowFor($lesson->date, $lesson->startTime, $lesson->endTime, $now)->value,
            'phase' => $now < $start ? 'upcoming' : ($now <= $end ? 'running' : 'ended'),
            'minutes_to_start' => intdiv($start->getTimestamp() - $now->getTimestamp(), 60),
            'minutes_to_end' => intdiv($end->getTimestamp() - $now->getTimestamp(), 60),
            'session' => $session,
        ];
    }
}
