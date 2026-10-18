<?php

namespace App\Application\Leave\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Leave\Results\SaveLeaveTypeResult;
use App\Application\Leave\Support\LeaveTransaction;
use App\Domain\Leave\Data\LeaveTypeFields;
use App\Domain\Leave\Exceptions\LeaveRuleViolation;
use App\Domain\Leave\Repositories\LeaveTypeRepositoryInterface;

final class SaveLeaveTypeHandler implements CommandHandler
{
    public const COMMAND_NAME = 'Leave.SaveLeaveType';

    public function __construct(
        private readonly LeaveTransaction $tx,
        private readonly LeaveTypeRepositoryInterface $types,
    ) {}

    public function handle(Command $command): SaveLeaveTypeResult
    {
        assert($command instanceof SaveLeaveTypeCommand);
        $command->fields->assertValid();

        return $this->tx->run(SaveLeaveTypeResult::class, $command->idempotencyKey, self::COMMAND_NAME, function () use ($command): array {
            $at = (new \DateTimeImmutable)->format('Y-m-d H:i:s');
            if ($command->typeId === null) {
                $code = strtoupper(trim((string) $command->code));
                $terms = $command->terms ?? throw LeaveRuleViolation::of('policy_required');
                $terms->assertValid();
                if (! LeaveTypeFields::validCode($code)) {
                    throw LeaveRuleViolation::of('type_code_invalid');
                }
                if ($this->types->codeTaken($command->schoolId, $code)) {
                    throw LeaveRuleViolation::of('type_code_taken');
                }
                $id = $this->types->create($command->schoolId, $code, $command->fields, $command->actorUserId, $at);
                $this->types->publishPolicy($command->schoolId, $id, $terms, substr($at, 0, 10), $command->actorUserId);

                return ['data' => ['type_id' => $id], 'event' => LeaveTransaction::event('type', $id, $command->schoolId, 'created', $command->actorUserId)];
            }

            $type = $this->types->find($command->schoolId, $command->typeId) ?? throw LeaveRuleViolation::typeNotFound();
            // The unit counts every past request: fixed once a request was filed under the type.
            if ($type->unit !== $command->fields->unit && $this->types->hasRequests($command->schoolId, $type->id)) {
                throw LeaveRuleViolation::of('type_unit_locked');
            }
            $this->types->update($type->id, $command->fields, $at);

            return ['data' => ['type_id' => $type->id], 'event' => LeaveTransaction::event('type', $type->id, $command->schoolId, 'updated', $command->actorUserId)];
        });
    }
}
