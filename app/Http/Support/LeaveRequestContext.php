<?php

namespace App\Http\Support;

use App\Application\Attendance\Contracts\AttendanceClockInterface;
use App\Application\Attendance\Support\AttendancePolicyResolver;
use App\Application\Leave\Support\LeaveViewer;
use App\Infrastructure\Persistence\Eloquent\AttendanceSessionRecord;
use App\Infrastructure\Persistence\Eloquent\ScheduleRecord;
use App\Models\User;
use App\Security\Context\SchoolContext;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The tenant scope and the acting user of a leave request — from the session and the authenticated user only,
 * never from request fields (no client-supplied school, viewer scope or approver flag).
 */
final class LeaveRequestContext
{
    public function __construct(
        private readonly SchoolContext $schoolContext,
        private readonly AcademicYearContextResolver $years,
        private readonly AttendancePolicyResolver $policies,
        private readonly AttendanceClockInterface $clock,
    ) {}

    public function schoolId(): int
    {
        return $this->schoolContext->requireId();
    }

    public function academicYearId(Request $request, string $field = 'academic_year_id'): int
    {
        $raw = $request->input($field, $request->query($field));
        $yearId = $this->years->resolve(is_numeric($raw) ? (int) $raw : null, $request);
        if ($yearId === null) {
            throw new HttpException(409, 'No academic year is configured.');
        }

        return $yearId;
    }

    /** Today in the school's time zone (the attendance policy's) — not the server's UTC date. */
    public function today(int $academicYearId): string
    {
        return $this->policies->current($this->schoolId(), $academicYearId)->localDate($this->clock->now());
    }

    public function userId(Request $request): int
    {
        $user = $request->user();
        assert($user instanceof User);

        return (int) $user->id;
    }

    public function viewer(Request $request): LeaveViewer
    {
        $user = $request->user();
        assert($user instanceof User);

        return new LeaveViewer(
            (int) $user->id,
            $user->can('viewLeave'),
            $user->can('viewSensitiveLeave'),
            $user->can('viewLeaveBalances'),
            $user->can('viewExcuses', AttendanceSessionRecord::class),
        );
    }

    /**
     * Permission hints for the UI (the server re-checks every action).
     *
     * @return array<string, bool>
     */
    public function abilities(Request $request): array
    {
        $user = $request->user();
        assert($user instanceof User);

        return [
            'view' => $user->can('viewLeave'),
            'request' => $user->can('requestLeave'),
            'manage' => $user->can('manageLeaveRequests'),
            'approve' => $user->can('approveLeave'),
            'cancel' => $user->can('cancelLeave'),
            'policies' => $user->can('manageLeavePolicies'),
            'balances' => $user->can('viewLeaveBalances'),
            'adjust' => $user->can('adjustLeaveBalances'),
            'sensitive' => $user->can('viewSensitiveLeave'),
            'reports' => $user->can('viewLeaveReports'),
            'assign_cover' => $user->can('createException', ScheduleRecord::class),
            'holidays' => $user->can('manageSchools') || $user->can('view', ScheduleRecord::class),
            'student_excuses' => $user->can('viewExcuses', AttendanceSessionRecord::class),
        ];
    }
}
