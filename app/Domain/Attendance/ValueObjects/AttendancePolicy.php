<?php

namespace App\Domain\Attendance\ValueObjects;

/**
 * School + academic-year attendance rules (attendance.policies). When a school has not saved a policy the
 * defaults below apply (id = null). Every session remembers the policy row that governed it, so a later
 * policy change never rewrites history.
 */
final readonly class AttendancePolicy
{
    public function __construct(
        public ?int $id,
        public string $timezone,
        public int $openBeforeMinutes = 10,
        public int $submitDeadlineMinutes = 120,
        public int $editWindowDays = 0,
        public int $lateGraceMinutes = 5,
        public ?int $lateAsAbsentMinutes = null,
        public bool $requireComplete = true,
        public int $defaultMark = 0,
        public bool $teacherCanMarkExcused = false,
        public int $absenceAlertCount = 3,
        public int $absenceAlertWindowDays = 14,
        public int $absenceRateAlertPercent = 20,
        public int $lateAlertCount = 3,
        public int $returnedAlertCount = 3,
        public string $staffDayStart = '07:30',
        public int $staffLateGraceMinutes = 10,
        public int $checkinCodeTtlMinutes = 10,
        public bool $qrEnabled = true,
        public bool $nfcEnabled = false,
        public int $versionNo = 0,
    ) {}

    public static function defaults(string $timezone): self
    {
        return new self(null, $timezone);
    }

    public function zone(): \DateTimeZone
    {
        try {
            return new \DateTimeZone($this->timezone);
        } catch (\Exception) {
            return new \DateTimeZone('UTC');
        }
    }

    /** The school-local calendar date of an instant (never the device clock of the browser). */
    public function localDate(\DateTimeImmutable $instant): string
    {
        return $instant->setTimezone($this->zone())->format('Y-m-d');
    }

    /** A lesson boundary («2026-10-11» + «08:45») as an absolute instant in the school's zone. */
    public function at(string $date, string $time): \DateTimeImmutable
    {
        return new \DateTimeImmutable($date.' '.substr($time, 0, 5).':00', $this->zone());
    }

    public function windowFor(string $date, string $startTime, string $endTime, \DateTimeImmutable $now): LessonWindow
    {
        $opens = $this->at($date, $startTime)->modify("-{$this->openBeforeMinutes} minutes");
        $deadline = $this->at($date, $endTime)->modify("+{$this->submitDeadlineMinutes} minutes");
        $closes = $this->at($date, '23:59')->modify("+{$this->editWindowDays} days");

        return match (true) {
            $now < $opens => LessonWindow::NotYetOpen,
            $now <= $deadline => LessonWindow::Open,
            $now <= $closes => LessonWindow::LateSubmission,
            default => LessonWindow::Closed,
        };
    }

    /**
     * Late arrivals under policy: within the grace period the student counts as present; at or beyond
     * late_as_absent_minutes (when set) as absent; otherwise late.
     */
    public function classifyLate(?int $minutesLate): AttendanceRecordStatus
    {
        if ($minutesLate === null) {
            return AttendanceRecordStatus::Late;
        }
        if ($minutesLate <= $this->lateGraceMinutes) {
            return AttendanceRecordStatus::Present;
        }
        if ($this->lateAsAbsentMinutes !== null && $minutesLate >= $this->lateAsAbsentMinutes) {
            return AttendanceRecordStatus::Absent;
        }

        return AttendanceRecordStatus::Late;
    }

    /** Staff check-in: minutes after the day start beyond the grace period make the teacher late. */
    public function staffMinutesLate(\DateTimeImmutable $checkIn): int
    {
        $local = $checkIn->setTimezone($this->zone());
        $start = $this->at($local->format('Y-m-d'), $this->staffDayStart);
        $minutes = intdiv($local->getTimestamp() - $start->getTimestamp(), 60);

        return $minutes > $this->staffLateGraceMinutes ? $minutes : 0;
    }

    /**
     * @return array<string, int|bool|string|null>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'version_no' => $this->versionNo,
            'timezone' => $this->timezone,
            'open_before_minutes' => $this->openBeforeMinutes,
            'submit_deadline_minutes' => $this->submitDeadlineMinutes,
            'edit_window_days' => $this->editWindowDays,
            'late_grace_minutes' => $this->lateGraceMinutes,
            'late_as_absent_minutes' => $this->lateAsAbsentMinutes,
            'require_complete' => $this->requireComplete,
            'default_mark' => $this->defaultMark,
            'teacher_can_mark_excused' => $this->teacherCanMarkExcused,
            'absence_alert_count' => $this->absenceAlertCount,
            'absence_alert_window_days' => $this->absenceAlertWindowDays,
            'absence_rate_alert_percent' => $this->absenceRateAlertPercent,
            'late_alert_count' => $this->lateAlertCount,
            'returned_alert_count' => $this->returnedAlertCount,
            'staff_day_start' => $this->staffDayStart,
            'staff_late_grace_minutes' => $this->staffLateGraceMinutes,
            'checkin_code_ttl_minutes' => $this->checkinCodeTtlMinutes,
            'qr_enabled' => $this->qrEnabled,
            'nfc_enabled' => $this->nfcEnabled,
        ];
    }
}
