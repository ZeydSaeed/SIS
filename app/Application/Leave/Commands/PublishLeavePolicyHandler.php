<?php

namespace App\Application\Leave\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Leave\Results\PublishLeavePolicyResult;
use App\Application\Leave\Support\LeaveTransaction;
use App\Domain\Leave\Exceptions\LeaveRuleViolation;
use App\Domain\Leave\Repositories\LeaveTypeRepositoryInterface;

final class PublishLeavePolicyHandler implements CommandHandler
{
    public const COMMAND_NAME = 'Leave.PublishLeavePolicy';

    public function __construct(
        private readonly LeaveTransaction $tx,
        private readonly LeaveTypeRepositoryInterface $types,
    ) {}

    public function handle(Command $command): PublishLeavePolicyResult
    {
        assert($command instanceof PublishLeavePolicyCommand);
        $command->terms->assertValid();

        return $this->tx->run(PublishLeavePolicyResult::class, $command->idempotencyKey, self::COMMAND_NAME, function () use ($command): array {
            $type = $this->types->find($command->schoolId, $command->typeId) ?? throw LeaveRuleViolation::typeNotFound();
            $policyId = $this->types->publishPolicy($command->schoolId, $type->id, $command->terms, (new \DateTimeImmutable)->format('Y-m-d'), $command->actorUserId);

            return [
                'data' => ['type_id' => $type->id, 'policy_id' => $policyId],
                'event' => LeaveTransaction::event('policy', $policyId, $command->schoolId, 'published', $command->actorUserId, ['type_id' => $type->id]),
            ];
        });
    }
}
