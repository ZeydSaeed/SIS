<?php

namespace App\Application\Leave\Queries;

use App\Application\Attendance\Contracts\LessonSourceInterface;
use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Application\Leave\Contracts\LeaveReadRepositoryInterface;
use App\Application\Leave\Contracts\LeaveStaffDirectoryInterface;
use App\Application\Leave\Support\LeaveCalendar;
use App\Domain\Attendance\Data\LessonSlot;
use App\Domain\Leave\Data\LeaveRequester;
use App\Domain\Leave\Services\LeaveDurationCalculator;
use App\Domain\Leave\ValueObjects\LeaveAudience;
use App\Domain\Leave\ValueObjects\LeaveStatus;
use App\Domain\Leave\ValueObjects\RequesterKind;

/**
 * «الحصص المتأثرة»: the lessons a teacher's leave touches — on each working day of the range, the teacher's lessons
 * of the effective timetable (as lead or co-teacher), with the substitute already assigned for that date if any.
 * Read only: cover is assigned through the timetable's own substitution command and permissions, never here, and
 * the published timetable is never changed.
 */
final class GetLeaveCoverageHandler implements QueryHandler
{
    public function __construct(
        private readonly LeaveReadRepositoryInterface $read,
        private readonly LessonSourceInterface $lessons,
        private readonly LeaveCalendar $calendar,
        private readonly LeaveStaffDirectoryInterface $staff,
    ) {}

    /** @return array<string, mixed>|null */
    public function handle(Query $query): ?array
    {
        assert($query instanceof GetLeaveCoverageQuery);
        $row = $this->read->request($query->schoolId, $query->requestId);
        if ($row === null || ! $query->viewer->seesAll || (int) $row['requester_kind'] !== RequesterKind::Teacher->value) {
            return null;
        }
        $status = LeaveStatus::from((int) $row['status']);
        $teacherId = (int) $row['person_id'];
        $yearId = (int) $row['academic_year_id'];
        $dates = LeaveDurationCalculator::workingDates(
            (string) $row['date_from'], (string) $row['date_to'],
            $this->calendar->workingWeekdays($query->schoolId, $yearId), $this->calendar->holidayDates($query->schoolId, $yearId),
        );
        $pages = max(1, (int) ceil(count($dates) / GetLeaveCoverageQuery::DAYS_PER_PAGE));
        $page = min(max(1, $query->page), $pages);
        $names = array_column(array_map(
            static fn (LeaveRequester $p): array => ['id' => $p->personId, 'name' => $p->name],
            $this->staff->staff($query->schoolId, $yearId, LeaveAudience::Teachers),
        ), 'name', 'id');

        $out = [];
        foreach (array_slice($dates, ($page - 1) * GetLeaveCoverageQuery::DAYS_PER_PAGE, GetLeaveCoverageQuery::DAYS_PER_PAGE) as $date) {
            foreach ($this->lessons->lessonsOn($query->schoolId, $yearId, $date) as $lesson) {
                if ($lesson->teacherId === $teacherId || $lesson->coTeacherId === $teacherId) {
                    $out[] = $this->row($lesson, $teacherId, $names);
                }
            }
        }

        return [
            'request_id' => $query->requestId,
            'status' => $status->value,
            'teacher_id' => $teacherId,
            'academic_year_id' => $yearId,
            'lessons' => $out,
            'page' => $page,
            'pages' => $pages,
            'working_days' => count($dates),
        ];
    }

    /**
     * @param  array<int, string>  $names
     * @return array<string, mixed>
     */
    private function row(LessonSlot $lesson, int $teacherId, array $names): array
    {
        return $lesson->toArray() + [
            'role' => $lesson->teacherId === $teacherId ? 'lead' : 'co',
            'substitute_name' => $lesson->substituteTeacherId === null ? null : ($names[$lesson->substituteTeacherId] ?? null),
            'covered' => $lesson->substituteTeacherId !== null && $lesson->substituteTeacherId !== $teacherId,
        ];
    }
}
