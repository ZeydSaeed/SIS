<?php

namespace App\Application\Attendance\Commands;

use App\Application\Attendance\Results\SaveAttendancePolicyResult;
use App\Application\Attendance\Support\AttendancePolicyResolver;
use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Domain\Attendance\Events\AttendanceWorkflowEvent;
use App\Domain\Attendance\Exceptions\AttendanceRuleViolation;
use App\Domain\Attendance\Repositories\AttendancePolicyRepositoryInterface;

/** Policy changes never touch existing sheets or records — they only govern sheets opened afterwards. */
final class SaveAttendancePolicyHandler implements CommandHandler
{
    public const COMMAND_NAME = 'SaveAttendancePolicy';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly AttendancePolicyRepositoryInterface $policies,
        private readonly AttendancePolicyResolver $resolver,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): SaveAttendancePolicyResult
    {
        assert($command instanceof SaveAttendancePolicyCommand);

        $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
        if ($cached !== null) {
            return SaveAttendancePolicyResult::fromIdempotency($cached);
        }
        if (! in_array($command->values->timezone, \DateTimeZone::listIdentifiers(), true)) {
            throw AttendanceRuleViolation::policyInvalid('timezone');
        }
        if ($command->values->lateAsAbsentMinutes !== null && $command->values->lateAsAbsentMinutes <= $command->values->lateGraceMinutes) {
            throw AttendanceRuleViolation::policyInvalid('late_as_absent_minutes');
        }

        $data = $this->unitOfWork->transaction(function () use ($command): array {
            $before = $this->resolver->current($command->schoolId, $command->academicYearId);
            $id = $this->policies->saveNewVersion($command->schoolId, $command->academicYearId, $command->values, $command->actorUserId);
            $this->outbox->stage(new AttendanceWorkflowEvent(
                AttendanceWorkflowEvent::POLICY_CHANGED, $command->schoolId, $command->academicYearId, 'attendance_policy', $id, $command->actorUserId,
                $before->toArray(), $command->values->toArray(), new \DateTimeImmutable,
            ));

            return ['policy_id' => $id];
        });

        $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, $data);

        return SaveAttendancePolicyResult::success($data);
    }
}
