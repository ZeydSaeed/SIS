<?php

namespace App\Application\Attendance\Commands;

use App\Application\Attendance\Results\UpdateAttendanceAlertResult;
use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Domain\Attendance\Exceptions\AttendanceRuleViolation;
use App\Domain\Attendance\Repositories\AttendanceAlertRepositoryInterface;

final class UpdateAttendanceAlertHandler implements CommandHandler
{
    public const COMMAND_NAME = 'UpdateAttendanceAlert';

    public function __construct(
        private readonly AttendanceAlertRepositoryInterface $alerts,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): UpdateAttendanceAlertResult
    {
        assert($command instanceof UpdateAttendanceAlertCommand);

        $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
        if ($cached !== null) {
            return UpdateAttendanceAlertResult::fromIdempotency($cached);
        }
        if (! in_array($command->status, [2, 3, 4], true)) {
            throw AttendanceRuleViolation::statusNotAllowed($command->status);
        }
        $note = $command->note !== null && trim($command->note) !== '' ? trim($command->note) : null;
        if ($command->status === 4 && $note === null) {
            throw AttendanceRuleViolation::reasonRequired();
        }
        if (! $this->alerts->setStatus($command->schoolId, $command->alertId, $command->status, $command->actorUserId, $note)) {
            throw AttendanceRuleViolation::alertNotFound();
        }

        $data = ['alert_id' => $command->alertId, 'status' => $command->status];
        $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, $data);

        return UpdateAttendanceAlertResult::success($data);
    }
}
