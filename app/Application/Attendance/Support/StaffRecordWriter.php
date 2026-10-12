<?php

namespace App\Application\Attendance\Support;

use App\Application\Attendance\Commands\RecordStaffAttendanceCommand;
use App\Application\Contracts\OutboxRepository;
use App\Domain\Attendance\Events\AttendanceWorkflowEvent;
use App\Domain\Attendance\Exceptions\AttendanceRuleViolation;
use App\Domain\Attendance\Repositories\AttendanceWriteRepositoryInterface;
use App\Domain\Attendance\Repositories\StaffAttendanceRepositoryInterface;
use App\Domain\Attendance\ValueObjects\EvidenceMethod;
use App\Domain\Attendance\ValueObjects\StaffAttendanceStatus;

/**
 * Administrative staff attendance: validation, the school-local → instant conversion of times, and the
 * create-or-correct write (a correction needs the version the user saw and a reason; before/after go to the outbox).
 */
final class StaffRecordWriter
{
    public function __construct(
        private readonly StaffAttendanceRepositoryInterface $staff,
        private readonly AttendanceWriteRepositoryInterface $attendance,
        private readonly AttendancePolicyResolver $policies,
        private readonly OutboxRepository $outbox,
    ) {}

    /**
     * @return array{status: int, check_in_at: ?string, check_out_at: ?string, minutes_late: ?int, method: int, notes: ?string}
     */
    public function validatedValues(RecordStaffAttendanceCommand $command): array
    {
        if (StaffAttendanceStatus::tryFrom($command->status) === null) {
            throw AttendanceRuleViolation::statusNotAllowed($command->status);
        }
        if (! $this->staff->teacherBelongsToSchool($command->teacherId, $command->schoolId, $command->academicYearId)) {
            throw AttendanceRuleViolation::teacherNotInSchool();
        }
        if (! $this->attendance->academicYearContainsDate($command->academicYearId, $command->workDate)) {
            throw AttendanceRuleViolation::dateOutsideYear();
        }

        // Times are entered as school-local HH:MM and stored as absolute instants.
        $policy = $this->policies->current($command->schoolId, $command->academicYearId);
        $at = static fn (?string $time): ?string => $time !== null && $time !== '' ? $policy->at($command->workDate, $time)->format(DATE_ATOM) : null;
        $notes = $command->notes !== null ? trim($command->notes) : '';

        return [
            'status' => $command->status,
            'check_in_at' => $at($command->checkInAt),
            'check_out_at' => $at($command->checkOutAt),
            'minutes_late' => $command->status === StaffAttendanceStatus::Late->value ? $command->minutesLate : null,
            'method' => EvidenceMethod::Administrative->value,
            'notes' => $notes === '' ? null : $notes,
        ];
    }

    /**
     * Call inside the handler's transaction.
     *
     * @param  array{status: int, check_in_at: ?string, check_out_at: ?string, minutes_late: ?int, method: int, notes: ?string}  $values
     * @return array{record_id: int, version: int, corrected: bool}
     */
    public function write(RecordStaffAttendanceCommand $command, array $values): array
    {
        $existing = $this->staff->lockForDay($command->schoolId, $command->teacherId, $command->workDate);
        if ($existing === null) {
            $id = $this->staff->insert($command->schoolId, $command->academicYearId, $command->teacherId, $command->workDate, $values, $command->actorUserId);
            $this->stage(AttendanceWorkflowEvent::STAFF_RECORDED, $command, $id, [], $values);

            return ['record_id' => $id, 'version' => 1, 'corrected' => false];
        }

        if ($command->expectedVersion !== $existing->version) {
            throw AttendanceRuleViolation::staffRecordConflict();
        }
        $reason = $command->reason !== null ? trim($command->reason) : '';
        if ($reason === '') {
            throw AttendanceRuleViolation::reasonRequired();
        }
        if (! $this->staff->update($existing->id, $existing->version, $values, $command->actorUserId)) {
            throw AttendanceRuleViolation::staffRecordConflict();
        }
        $this->stage(AttendanceWorkflowEvent::STAFF_CORRECTED, $command, $existing->id, $existing->toArray(), $values + ['reason' => $reason]);

        return ['record_id' => $existing->id, 'version' => $existing->version + 1, 'corrected' => true];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function stage(string $name, RecordStaffAttendanceCommand $command, int $recordId, array $before, array $after): void
    {
        $this->outbox->stage(new AttendanceWorkflowEvent(
            $name, $command->schoolId, $command->academicYearId, 'attendance_staff_record', $recordId, $command->actorUserId,
            $before, $after + ['teacher_id' => $command->teacherId, 'work_date' => $command->workDate],
            new \DateTimeImmutable,
        ));
    }
}
