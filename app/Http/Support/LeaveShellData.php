<?php

namespace App\Http\Support;

use App\Application\Leave\Contracts\LeaveReadRepositoryInterface;
use App\Application\Leave\Contracts\LeaveStaffDirectoryInterface;
use App\Domain\Academic\Repositories\HolidayRepositoryInterface;
use App\Domain\Attendance\ValueObjects\CalendarDate;
use App\Domain\Leave\Repositories\LeaveTypeRepositoryInterface;
use Illuminate\Http\Request;

/**
 * The one source of the leave pages' side panel (day card + calendar + leave-rate card): the shown day (`?date=`, else
 * today) with how many of the school's staff are on approved leave that day. Read through the leave read side, scoped
 * to what the viewer may see — the same counts as the dashboard cards.
 */
final class LeaveShellData
{
    public function __construct(
        private readonly LeaveRequestContext $context,
        private readonly LeaveReadRepositoryInterface $read,
        private readonly LeaveStaffDirectoryInterface $staff,
        private readonly LeaveTypeRepositoryInterface $types,
        private readonly HolidayRepositoryInterface $holidays,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function build(Request $request): ?array
    {
        $user = $request->user();
        if ($user === null || ! $user->can('accessLeave')) {
            return null;
        }

        try {
            $schoolId = $this->context->schoolId();
            $yearId = $this->context->academicYearId($request);
            $today = $this->context->today($yearId);
            $asked = $request->query('date');
            $date = is_string($asked) && CalendarDate::valid($asked) ? $asked : $today;
            $own = $this->context->viewer($request)->ownScope();
            $counts = $this->read->dashboardCounts($schoolId, $yearId, $date, $own);
            $headcount = count($this->staff->staff($schoolId, $yearId));
            $away = (int) ($counts['away_today'] ?? 0);

            return [
                'date' => $date,
                'today' => $today,
                'weekday' => (int) (new \DateTimeImmutable($date))->format('w') + 1,
                'totals' => [
                    'staff' => $headcount,
                    'away' => $away,
                    'teachers_away' => (int) ($counts['teachers_away_today'] ?? 0),
                    'pending' => (int) ($counts['pending'] ?? 0),
                    'rate' => $headcount > 0 ? round($away * 100 / $headcount, 1) : null,
                ],
                'counts' => $this->railCounts($request, $schoolId, $yearId, $date, $own, $away, $headcount),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * The number on every rail icon, the same count the section's own table shows: today's absentees, requests, balance rows,
     * the month's calendar events, types, staff, the month's student excuses and the year's holidays.
     *
     * @return array<string, int>
     */
    private function railCounts(Request $request, int $schoolId, int $yearId, string $date, ?int $own, int $away, int $headcount): array
    {
        try {
            return $this->countSections($request, $schoolId, $yearId, $date, $own, $away, $headcount);
        } catch (\Throwable) {
            // A count that cannot be read leaves its icon without a number; the page itself still renders.
            return [];
        }
    }

    /** @return array<string, int> */
    private function countSections(Request $request, int $schoolId, int $yearId, string $date, ?int $own, int $away, int $headcount): array
    {
        $viewer = $this->context->viewer($request);
        $monthStart = substr($date, 0, 8).'01';
        $monthEnd = (new \DateTimeImmutable($monthStart))->modify('last day of this month')->format('Y-m-d');

        return [
            'dashboard' => $away,
            'requests' => (int) ($this->read->requests($schoolId, ['year_id' => $yearId, 'own_user_id' => $own], 1, 1)['total'] ?? 0),
            'balances' => $viewer->seesBalances ? (int) ($this->read->balances($schoolId, $yearId, [], 1, 1)['total'] ?? 0) : 0,
            'calendar' => count($this->read->calendar($schoolId, $monthStart, $monthEnd, [], $own)),
            'types' => count($this->types->list($schoolId)),
            'reports' => $headcount,
            'students' => $viewer->seesStudentExcuses ? count($this->read->studentExcuses($schoolId, $monthStart, $monthEnd)) : 0,
            'holidays' => count($this->holidays->listVisible($schoolId, $yearId)),
        ];
    }
}
