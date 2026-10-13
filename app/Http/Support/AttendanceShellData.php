<?php

namespace App\Http\Support;

use App\Application\Attendance\Contracts\AttendanceClockInterface;
use App\Application\Attendance\Contracts\AttendanceReportReadInterface;
use App\Application\Attendance\Support\AttendancePolicyResolver;
use App\Domain\Attendance\Services\AttendanceStatistics;
use App\Domain\Attendance\ValueObjects\CalendarDate;
use App\Infrastructure\Persistence\Eloquent\AttendanceSessionRecord;
use Illuminate\Http\Request;

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

            return [
                'date' => $date,
                'today' => $today,
                'now' => $now->setTimezone($policy->zone())->format('H:i'),
                'weekday' => (int) (new \DateTimeImmutable($date))->format('w') + 1,
                'totals' => $totals + AttendanceStatistics::rates($totals),
                'abilities' => $this->context->abilities($request),
            ];
        } catch (\Throwable) {
            return null;
        }
    }
}
