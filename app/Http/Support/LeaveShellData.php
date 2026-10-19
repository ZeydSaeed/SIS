<?php

namespace App\Http\Support;

use App\Application\Leave\Contracts\LeaveReadRepositoryInterface;
use App\Application\Leave\Contracts\LeaveStaffDirectoryInterface;
use App\Domain\Attendance\ValueObjects\CalendarDate;
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
            ];
        } catch (\Throwable) {
            return null;
        }
    }
}
