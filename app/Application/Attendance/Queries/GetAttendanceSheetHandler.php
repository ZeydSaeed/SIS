<?php

namespace App\Application\Attendance\Queries;

use App\Application\Attendance\Contracts\AttendanceClockInterface;
use App\Application\Attendance\Contracts\AttendanceWorkspaceReadInterface;
use App\Application\Attendance\DTOs\AttendanceViewDTO;
use App\Application\Attendance\Support\AttendancePolicyResolver;
use App\Application\Attendance\Support\SheetPermissions;
use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Domain\Attendance\Data\RosterEntry;
use App\Domain\Attendance\Exceptions\SessionNotFoundException;
use App\Domain\Attendance\Repositories\AttendanceSheetRepositoryInterface;
use App\Domain\Attendance\Services\LessonAccessGuard;
use App\Domain\Attendance\Services\SheetTally;
use App\Domain\Attendance\ValueObjects\LessonWindow;

/**
 * The sheet screen: lesson header, roster from the real enrollments of the section (and group) on that date,
 * stored marks, counts, history, and what the viewer may do — the server re-checks every action anyway.
 */
final class GetAttendanceSheetHandler implements QueryHandler
{
    public function __construct(
        private readonly AttendanceWorkspaceReadInterface $read,
        private readonly AttendanceSheetRepositoryInterface $sheets,
        private readonly AttendancePolicyResolver $policies,
        private readonly AttendanceClockInterface $clock,
    ) {}

    public function handle(Query $query): AttendanceViewDTO
    {
        assert($query instanceof GetAttendanceSheetQuery);

        $header = $this->read->sheetHeader($query->schoolId, $query->sessionId);
        if ($header === null) {
            throw SessionNotFoundException::forId($query->sessionId);
        }
        LessonAccessGuard::assertCanWork($query->actor->teacherId, (int) $header['teacher_id'], $header['co_teacher_id'], $query->canViewAny || $query->actor->isReviewer);

        $year = (int) $header['academic_year_id'];
        $roster = $this->sheets->roster($query->schoolId, $year, (int) $header['section_id'], $header['group_id'], (string) $header['session_date']);
        $enrollmentIds = array_map(static fn (RosterEntry $r): int => $r->enrollmentId, $roster);
        $policy = $this->policies->forSheet($header['policy_id'], $query->schoolId, $year);
        $window = $header['start_time'] !== null && $header['end_time'] !== null
            ? $policy->windowFor((string) $header['session_date'], (string) $header['start_time'], (string) $header['end_time'], $this->clock->now())
            : LessonWindow::Open;

        return new AttendanceViewDTO([
            'session' => $header,
            'rows' => $this->read->sheetRows($query->schoolId, $query->sessionId, $year, $enrollmentIds),
            'off_roster' => $this->read->offRosterRows($query->schoolId, $query->sessionId, $year, $enrollmentIds),
            'counts' => SheetTally::of($roster, $this->sheets->statuses($query->sessionId, $year)),
            'events' => $this->read->sheetEvents($query->schoolId, $query->sessionId),
            'policy' => [
                'default_mark' => $policy->defaultMark,
                'require_complete' => $policy->requireComplete,
                'teacher_can_mark_excused' => $policy->teacherCanMarkExcused,
                'late_grace_minutes' => $policy->lateGraceMinutes,
                'late_as_absent_minutes' => $policy->lateAsAbsentMinutes,
                'submit_deadline_minutes' => $policy->submitDeadlineMinutes,
            ],
            'window' => $window->value,
            'permissions' => SheetPermissions::for($query->actor, $query->canCorrect, $header, $window),
        ]);
    }
}
