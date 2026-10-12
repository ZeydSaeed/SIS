<?php

namespace App\Application\Attendance\Commands;

use App\Application\Attendance\Contracts\AttendanceAnalyticsReadInterface;
use App\Application\Attendance\Contracts\AttendanceClockInterface;
use App\Application\Attendance\Results\DetectAttendanceAlertsResult;
use App\Application\Attendance\Support\AttendancePolicyResolver;
use App\Application\Attendance\Support\TeacherAlertInputs;
use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Domain\Attendance\Repositories\AttendanceAlertRepositoryInterface;
use App\Domain\Attendance\Services\AttendanceAlertRules;

/** Deterministic rules → reviewable alerts (deduplicated per rule · subject · day). Nothing is changed or decided. */
final class DetectAttendanceAlertsHandler implements CommandHandler
{
    public const COMMAND_NAME = 'DetectAttendanceAlerts';

    public function __construct(
        private readonly AttendanceAnalyticsReadInterface $analytics,
        private readonly TeacherAlertInputs $teacherInputs,
        private readonly AttendanceAlertRepositoryInterface $alerts,
        private readonly AttendancePolicyResolver $policies,
        private readonly AttendanceClockInterface $clock,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): DetectAttendanceAlertsResult
    {
        assert($command instanceof DetectAttendanceAlertsCommand);

        $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
        if ($cached !== null) {
            return DetectAttendanceAlertsResult::fromIdempotency($cached);
        }

        $policy = $this->policies->current($command->schoolId, $command->academicYearId);
        $from = (new \DateTimeImmutable($command->date))->modify('-'.($policy->absenceAlertWindowDays - 1).' days')->format('Y-m-d');
        $school = $command->schoolId;
        $year = $command->academicYearId;

        $candidates = [
            ...AttendanceAlertRules::forStudents($this->analytics->studentWindowStats($school, $year, $from, $command->date), $policy),
            ...AttendanceAlertRules::forStudentSubjects($this->analytics->studentSubjectAbsences($school, $year, $from, $command->date), $policy),
            ...AttendanceAlertRules::forConflicts($this->analytics->attendanceConflicts($school, $year, $command->date)),
            ...AttendanceAlertRules::forSectionSpikes($this->analytics->sectionDay($school, $year, $command->date), $policy),
            ...AttendanceAlertRules::forTeachers($this->teacherInputs->collect($school, $year, $command->date, $from, $policy, $this->clock->now()), $policy),
        ];
        $created = $this->alerts->record($school, $year, $from, $command->date, $candidates);

        $data = ['created' => $created, 'evaluated' => count($candidates), 'period_start' => $from, 'period_end' => $command->date];
        $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, $data);

        return DetectAttendanceAlertsResult::success($data);
    }
}
