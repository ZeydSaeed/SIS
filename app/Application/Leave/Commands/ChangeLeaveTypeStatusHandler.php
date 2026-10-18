<?php

namespace App\Application\Leave\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Leave\Results\ChangeLeaveTypeStatusResult;
use App\Application\Leave\Support\LeaveTransaction;
use App\Domain\Leave\Exceptions\LeaveRuleViolation;
use App\Domain\Leave\Repositories\LeaveTypeRepositoryInterface;

final class ChangeLeaveTypeStatusHandler implements CommandHandler
{
    public const COMMAND_NAME = 'Leave.ChangeLeaveTypeStatus';

    public function __construct(
        private readonly LeaveTransaction $tx,
        private readonly LeaveTypeRepositoryInterface $types,
    ) {}

    public function handle(Command $command): ChangeLeaveTypeStatusResult
    {
        assert($command instanceof ChangeLeaveTypeStatusCommand);

        return $this->tx->run(ChangeLeaveTypeStatusResult::class, $command->idempotencyKey, self::COMMAND_NAME, function () use ($command): array {
            $type = $this->types->find($command->schoolId, $command->typeId) ?? throw LeaveRuleViolation::typeNotFound();
            $status = $command->active ? LeaveTypeRepositoryInterface::ACTIVE : LeaveTypeRepositoryInterface::INACTIVE;
            $this->types->setStatus($type->id, $status, (new \DateTimeImmutable)->format('Y-m-d H:i:s'));

            return [
                'data' => ['type_id' => $type->id, 'status' => $status],
                'event' => LeaveTransaction::event('type', $type->id, $command->schoolId, $command->active ? 'reactivated' : 'deactivated', $command->actorUserId),
            ];
        });
    }
}
