<?php

namespace App\Http\Support;

use App\Application\Attendance\Contracts\AttendanceClockInterface;
use App\Application\Attendance\Contracts\AttendanceReportReadInterface;
use App\Application\Attendance\Support\AttendancePolicyResolver;
use App\Domain\Attendance\Services\AttendanceStatistics;
use App\Domain\Attendance\ValueObjects\CalendarDate;
use App\Infrastructure\Persistence\Eloquent\AttendanceSessionRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The one source of the fixed attendance shell (side calendar + day card + quick-link rail): the school-wide totals of
 * the shown day (`?date=`, else today) and the user's abilities. Shared with every /attendance page, so the card never
 * differs from page to page.
 */
final class AttendanceShellData
{
    public function __construct(
        private readonly AttendanceRequestContext $context,
        private readonly AttendancePolicyResolver $policies,
        private readonly AttendanceClockInterface $clock,
        private readonly AttendanceReportReadInterface $reports,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function build(Request $request): ?array
    {
        $user = $request->user();
        if ($user === null || ! $user->can('viewAny', AttendanceSessionRecord::class)) {
            return null;
        }

        try {
            $schoolId = $this->context->schoolId();
            $yearId = $this->context->academicYearId($request);
            $policy = $this->policies->current($schoolId, $yearId);
            $now = $this->clock->now();
            $today = $policy->localDate($now);
            // The shown day: `?date=`, else a one-day range (`date_from` = `date_to`) set by a sheet list, else today.
            $asked = $request->query('date');
            $from = $request->query('date_from');
            $to = $request->query('date_to');
            $date = $today;
            if (is_string($asked) && CalendarDate::valid($asked)) {
                $date = $asked;
            } elseif (is_string($from) && CalendarDate::valid($from) && ($to === null || $to === '' || $to === $from)) {
                $date = $from;
            }
            $totals = $this->reports->schoolTotals($schoolId, $yearId, $date, $date);
            $abilities = $this->context->abilities($request);

            return [
                'date' => $date,
                'today' => $today,
                'now' => $now->setTimezone($policy->zone())->format('H:i'),
                'weekday' => (int) (new \DateTimeImmutable($date))->format('w') + 1,
                'totals' => $totals + AttendanceStatistics::rates($totals),
                'abilities' => $abilities,
                'counts' => $this->railCounts($request, $schoolId, $yearId, $date, $totals),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * The number on every rail icon: my lessons of the shown day, the day's sheets, sheets waiting for review, the day's
     * absentees, staff records of the day, pending excuses, marks recorded that day, open alerts, policies.
     *
     * @param  array<string, int>  $totals
     * @return array<string, int>
     */
    private function railCounts(Request $request, int $schoolId, int $yearId, string $date, array $totals): array
    {
        $teacherId = $this->context->actor($request, $yearId)->teacherId;

        return [
            'my' => $teacherId === null ? 0 : DB::table('attendance.sessions')
                ->where('school_id', $schoolId)->where('academic_year_id', $yearId)
                ->where('status', '<>', 3)->where('session_date', $date)->where('teacher_id', $teacherId)->count(),
            'sessions' => (int) ($totals['sheets'] ?? 0),
            'review' => DB::table('attendance.sessions')
                ->where('school_id', $schoolId)->where('academic_year_id', $yearId)
                ->where('status', '<>', 3)->whereIn('sheet_status', [2, 3])->count(),
            'dashboard' => (int) ($totals['absent'] ?? 0),
            'staff' => DB::table('attendance.staff_records')->where('school_id', $schoolId)->where('work_date', $date)->count(),
            'excuses' => DB::table('attendance.excuses')->where('school_id', $schoolId)->where('status', 1)->count(),
            'reports' => (int) ($totals['decided'] ?? 0),
            'alerts' => DB::table('attendance.alerts')->where('school_id', $schoolId)->where('status', 1)->count(),
            'settings' => DB::table('attendance.policies')->where('school_id', $schoolId)->count(),
        ];
    }
}
