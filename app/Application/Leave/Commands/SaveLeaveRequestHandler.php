<?php

namespace App\Application\Leave\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Leave\Results\SaveLeaveRequestResult;
use App\Application\Leave\Support\LeaveRequestPreparer;
use App\Application\Leave\Support\LeaveTransaction;
use App\Domain\Leave\Exceptions\LeaveRuleViolation;
use App\Domain\Leave\Repositories\LeaveRequestRepositoryInterface;
use App\Domain\Leave\Services\LeaveTransitions;
use App\Domain\Leave\ValueObjects\LeaveEventType;
use App\Domain\Leave\ValueObjects\LeaveStatus;

final class SaveLeaveRequestHandler implements CommandHandler
{
    public const COMMAND_NAME = 'Leave.SaveLeaveRequest';

    public function __construct(
        private readonly LeaveTransaction $tx,
        private readonly LeaveRequestRepositoryInterface $requests,
        private readonly LeaveRequestPreparer $prepare,
    ) {}

    public function handle(Command $command): SaveLeaveRequestResult
    {
        assert($command instanceof SaveLeaveRequestCommand);

        return $this->tx->run(SaveLeaveRequestResult::class, $command->idempotencyKey, self::COMMAND_NAME, function () use ($command): array {
            $requester = $this->prepare->requester($command->schoolId, $command->academicYearId, $command->kind, $command->personId);
            $this->prepare->assertMayActFor($requester, $command->actorUserId, $command->canManage);
            $type = $this->prepare->type($command->schoolId, $command->draft->leaveTypeId);
            $quantity = $this->prepare->quantity($command->schoolId, $command->academicYearId, $type, $command->kind, $command->draft);
            $policy = $type->policy ?? throw LeaveRuleViolation::of('type_inactive');

            if ($command->requestId === null) {
                $number = $this->requests->nextNumber($command->schoolId);
                $id = $this->requests->create(
                    $command->schoolId, $command->academicYearId, $number, $requester, $policy->id, $command->draft,
                    $quantity, $type->unit, $policy->approvalLevels, $command->actorUserId,
                );
                $this->requests->addEvent($command->schoolId, $id, LeaveEventType::Created, null, LeaveStatus::Draft, null, $command->actorUserId, null);

                return ['data' => ['request_id' => $id, 'request_no' => $number, 'quantity' => $quantity, 'version' => 1], 'event' => LeaveTransaction::event('request', $id, $command->schoolId, 'created', $command->actorUserId)];
            }

            $current = $this->requests->lock($command->schoolId, $command->requestId) ?? throw LeaveRuleViolation::requestNotFound();
            if ($current->kind !== $command->kind || $current->personId !== $command->personId || $current->academicYearId !== $command->academicYearId) {
                throw LeaveRuleViolation::of('requester_fixed');
            }
            LeaveTransitions::assert(LeaveTransitions::EDIT, $current->status);
            if ($command->expectedVersion !== $current->version
                || ! $this->requests->updateDraft($current->id, $current->version, $policy->id, $command->draft, $quantity, $type->unit, $policy->approvalLevels)) {
                throw LeaveRuleViolation::versionConflict();
            }
            $this->requests->addEvent($command->schoolId, $current->id, LeaveEventType::Updated, $current->status, $current->status, null, $command->actorUserId, null);

            return ['data' => ['request_id' => $current->id, 'request_no' => $current->requestNo, 'quantity' => $quantity, 'version' => $current->version + 1], 'event' => LeaveTransaction::event('request', $current->id, $command->schoolId, 'updated', $command->actorUserId)];
        });
    }
}
