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
        $teacherIds = $this->teacherIds($query);

        $base = [
            'date' => $date,
            'today' => $today,
            'now' => $now->setTimezone($policy->zone())->format('H:i'),
            'weekday' => (int) (new \DateTimeImmutable($date))->format('w') + 1,
            'timezone' => $policy->timezone,
            'holiday' => $this->sheets->isHoliday($query->schoolId, $query->academicYearId, $date),
        ];
        if ($teacherIds === []) {
            return new AttendanceViewDTO($base + ['teacher' => null, 'teachers' => [], 'lessons' => [], 'notices' => []]);
        }

        [$teachers, $rows, $notices] = $this->collect($query, $date, $teacherIds, $policy, $now);
        if (count($teacherIds) > 1) {
            usort($rows, static fn (array $a, array $b): int => [$a['start_time'] ?? '', $a['period_number'] ?? 0] <=> [$b['start_time'] ?? '', $b['period_number'] ?? 0]);
        }

        return new AttendanceViewDTO($base + [
            'teacher' => count($teacherIds) === 1 ? $teachers[0] : null,
            'teachers' => array_values(array_filter($teachers, 'is_array')),
            'lessons' => $rows,
            'notices' => array_slice($notices, 0, 10),
        ]);
    }

    /**
     * A reviewer may look at any teachers' days (combined); everyone else sees their own.
     *
     * @return list<int>
     */
    private function teacherIds(GetTeacherDayQuery $query): array
    {
        $ids = [];
        if ($query->actor->isReviewer) {
            $ids = array_values(array_unique(array_map('intval', $query->teacherIds)));
            if ($ids === [] && $query->teacherId !== null) {
                $ids = [$query->teacherId];
            }
        }

        return $ids === [] && $query->actor->teacherId !== null ? [$query->actor->teacherId] : $ids;
    }

    /**
     * @param  list<int>  $teacherIds
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>, 2: list<array<string, mixed>>}  teachers, lesson rows, notices
     */
    private function collect(GetTeacherDayQuery $query, string $date, array $teacherIds, AttendancePolicy $policy, \DateTimeImmutable $now): array
    {
        $teachers = [];
        $rows = [];
        $notices = [];
        foreach ($teacherIds as $teacherId) {
            $teacher = $this->teachers->teacher($teacherId);
            $teachers[] = $teacher;
            $lessons = $this->lessons->lessonsOn($query->schoolId, $query->academicYearId, $date, $teacherId);
            $sessions = $this->read->sessionsForLessons($query->schoolId, $date, array_map(static fn (LessonSlot $l): int => $l->scheduleId, $lessons));
            foreach ($lessons as $lesson) {
                $rows[] = $this->lessonRow($lesson, $sessions[$lesson->scheduleId] ?? null, $policy, $now) + [
                    'teacher_id' => $teacherId,
                    'teacher_name' => is_array($teacher) ? ($teacher['full_name'] ?? null) : null,
                ];
            }
            array_push($notices, ...$this->read->teacherNotices($query->schoolId, $query->academicYearId, $teacherId, 10));
        }

        return [$teachers, $rows, $notices];
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
