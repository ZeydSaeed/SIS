<?php

namespace App\Application\Attendance\Commands;

use App\Application\Attendance\Contracts\AttendanceClockInterface;
use App\Application\Attendance\Results\StaffCheckInResult;
use App\Application\Attendance\Support\AttendancePolicyResolver;
use App\Application\Attendance\Support\CheckinVerifier;
use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Domain\Attendance\Events\AttendanceWorkflowEvent;
use App\Domain\Attendance\Exceptions\AttendanceRuleViolation;
use App\Domain\Attendance\Repositories\StaffAttendanceRepositoryInterface;
use App\Domain\Attendance\ValueObjects\StaffAttendanceStatus;

final class StaffCheckInHandler implements CommandHandler
{
    public const COMMAND_NAME = 'StaffCheckIn';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly StaffAttendanceRepositoryInterface $staff,
        private readonly CheckinVerifier $verifier,
        private readonly AttendancePolicyResolver $policies,
        private readonly AttendanceClockInterface $clock,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): StaffCheckInResult
    {
        assert($command instanceof StaffCheckInCommand);

        $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
        if ($cached !== null) {
            return StaffCheckInResult::fromIdempotency($cached);
        }
        if ($command->teacherId === null || ! $this->staff->teacherBelongsToSchool($command->teacherId, $command->schoolId, $command->academicYearId)) {
            throw AttendanceRuleViolation::teacherProfileMissing();
        }

        $now = $this->clock->now();
        $policy = $this->policies->current($command->schoolId, $command->academicYearId);
        $method = $this->verifier->verify($command->schoolId, $command->code, $command->via, $policy, $now);
        $date = $policy->localDate($now);
        $teacherId = $command->teacherId;

        $data = $this->unitOfWork->transaction(function () use ($command, $now, $policy, $date, $teacherId, $method): array {
            $existing = $this->staff->lockForDay($command->schoolId, $teacherId, $date);
            if ($existing !== null) {
                if ($existing->checkInAt === null || $existing->checkOutAt !== null) {
                    throw AttendanceRuleViolation::alreadyCheckedIn();
                }
                $this->staff->update($existing->id, $existing->version, ['check_out_at' => $now->format(DATE_ATOM)] + $existing->toArray(), $command->actorUserId);

                return ['record_id' => $existing->id, 'action' => 'check_out', 'at' => $now->format(DATE_ATOM), 'work_date' => $date];
            }

            $minutesLate = $policy->staffMinutesLate($now);
            $values = [
                'status' => $minutesLate > 0 ? StaffAttendanceStatus::Late->value : StaffAttendanceStatus::Present->value,
                'check_in_at' => $now->format(DATE_ATOM),
                'check_out_at' => null,
                'minutes_late' => $minutesLate > 0 ? $minutesLate : null,
                'method' => $method->value,
                'notes' => null,
            ];
            $id = $this->staff->insert($command->schoolId, $command->academicYearId, $teacherId, $date, $values, $command->actorUserId);
            $this->outbox->stage(new AttendanceWorkflowEvent(
                AttendanceWorkflowEvent::STAFF_RECORDED, $command->schoolId, $command->academicYearId, 'attendance_staff_record', $id, $command->actorUserId,
                [], $values + ['teacher_id' => $teacherId, 'work_date' => $date], $now,
            ));

            return ['record_id' => $id, 'action' => 'check_in', 'at' => $now->format(DATE_ATOM), 'work_date' => $date, 'status' => $values['status'], 'minutes_late' => $values['minutes_late']];
        });

        $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, $data);

        return StaffCheckInResult::success($data);
    }
}
