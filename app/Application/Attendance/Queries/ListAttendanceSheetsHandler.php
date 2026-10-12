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
use App\Domain\Attendance\ValueObjects\SheetStatus;

/**
 * Sheets that exist (filtered, paginated) plus — for a single day — the timetable lessons whose submission
 * deadline passed without a submitted sheet («حصص لم يُرسل كشفها»).
 */
final class ListAttendanceSheetsHandler implements QueryHandler
{
    private const MISSING_CAP = 300;

    public function __construct(
        private readonly AttendanceWorkspaceReadInterface $read,
        private readonly LessonSourceInterface $lessons,
        private readonly AttendancePolicyResolver $policies,
        private readonly AttendanceClockInterface $clock,
    ) {}

    public function handle(Query $query): AttendanceViewDTO
    {
        assert($query instanceof ListAttendanceSheetsQuery);

        $board = $this->read->reviewBoard($query->schoolId, $query->academicYearId, $query->filters, $query->page, $query->perPage);
        $from = $query->filters['date_from'] ?? null;
        $singleDay = is_string($from) && $from === ($query->filters['date_to'] ?? null);

        return new AttendanceViewDTO($board + [
            'missing' => $singleDay ? $this->missing($query->schoolId, $query->academicYearId, $from) : [],
            'single_day' => $singleDay,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function missing(int $schoolId, int $academicYearId, string $date): array
    {
        $policy = $this->policies->current($schoolId, $academicYearId);
        $now = $this->clock->now();
        $lessons = $this->lessons->lessonsOn($schoolId, $academicYearId, $date);
        $sessions = $this->read->sessionsForLessons($schoolId, $date, array_map(static fn (LessonSlot $l): int => $l->scheduleId, $lessons));

        $out = [];
        foreach ($lessons as $lesson) {
            $deadline = $policy->at($date, $lesson->endTime)->modify("+{$policy->submitDeadlineMinutes} minutes");
            $session = $sessions[$lesson->scheduleId] ?? null;
            $submitted = $session !== null && ! SheetStatus::from((int) $session['sheet_status'])->allowsTeacherEditing();
            if ($now > $deadline && ! $submitted) {
                $out[] = $lesson->toArray() + ['session_id' => $session['session_id'] ?? null, 'sheet_status' => $session['sheet_status'] ?? null];
            }
            if (count($out) >= self::MISSING_CAP) {
                break;
            }
        }

        return $out;
    }
}
