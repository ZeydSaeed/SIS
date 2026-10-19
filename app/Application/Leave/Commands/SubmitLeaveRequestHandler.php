<?php

namespace App\Application\Leave\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Leave\Contracts\LeaveAttachmentStorageInterface;
use App\Application\Leave\Results\SubmitLeaveRequestResult;
use App\Application\Leave\Support\LeaveBalanceGuard;
use App\Application\Leave\Support\LeaveNotifier;
use App\Application\Leave\Support\LeaveRequestPreparer;
use App\Application\Leave\Support\LeaveTransaction;
use App\Domain\Leave\Exceptions\LeaveRuleViolation;
use App\Domain\Leave\Repositories\LeaveRequestRepositoryInterface;
use App\Domain\Leave\Services\LeaveTransitions;
use App\Domain\Leave\ValueObjects\LeaveEventType;
use App\Domain\Leave\ValueObjects\LeaveStatus;

final class SubmitLeaveRequestHandler implements CommandHandler
{
    public const COMMAND_NAME = 'Leave.SubmitLeaveRequest';

    public function __construct(
        private readonly LeaveTransaction $tx,
        private readonly LeaveRequestRepositoryInterface $requests,
        private readonly LeaveRequestPreparer $prepare,
        private readonly LeaveBalanceGuard $balance,
        private readonly LeaveAttachmentStorageInterface $attachments,
        private readonly LeaveNotifier $notifier,
    ) {}

    public function handle(Command $command): SubmitLeaveRequestResult
    {
        assert($command instanceof SubmitLeaveRequestCommand);

        return $this->tx->run(SubmitLeaveRequestResult::class, $command->idempotencyKey, self::COMMAND_NAME, function () use ($command): array {
            $request = $this->requests->lock($command->schoolId, $command->requestId) ?? throw LeaveRuleViolation::requestNotFound();
            $requester = $this->prepare->requester($request->schoolId, $request->academicYearId, $request->kind, $request->personId);
            $this->prepare->assertMayActFor($requester, $command->actorUserId, $command->canManage);
            LeaveTransitions::assert(LeaveTransitions::SUBMIT, $request->status);
            if ($command->expectedVersion !== $request->version) {
                throw LeaveRuleViolation::versionConflict();
            }

            // Counted again against today's calendar, under the type's current policy (frozen from now on).
            $type = $this->prepare->type($request->schoolId, $request->leaveTypeId);
            $draft = LeaveRequestPreparer::draftOf($request);
            $quantity = $this->prepare->quantity($request->schoolId, $request->academicYearId, $type, $request->kind, $draft);
            $policy = $type->policy ?? throw LeaveRuleViolation::of('type_inactive');
            if ($policy->requiresDocument && $this->attachments->count($request->schoolId, $request->id) === 0) {
                throw LeaveRuleViolation::of('document_required');
            }
            $this->requests->lockPerson($request->schoolId, $request->kind, $request->personId);
            if ($this->requests->overlaps($request->schoolId, $request->kind, $request->personId, $request->dateFrom, $request->dateTo, $request->id)) {
                throw LeaveRuleViolation::of('overlap');
            }
            $this->balance->assertCovers($request, $policy, $quantity);

            $this->requests->updateDraft($request->id, $request->version, $policy->id, $draft, $quantity, $type->unit, $policy->approvalLevels);
            $this->requests->transition($request->id, $request->version + 1, LeaveStatus::Pending, 0, null, null, true);
            $event = $request->status === LeaveStatus::InfoRequired ? LeaveEventType::Resubmitted : LeaveEventType::Submitted;
            $this->requests->addEvent($request->schoolId, $request->id, $event, $request->status, LeaveStatus::Pending, null, $command->actorUserId, null, ['quantity' => $quantity, 'policy_id' => $policy->id]);

            $this->notifier->awaitingApproval($request, $requester, $command->actorUserId);

            return [
                'data' => ['request_id' => $request->id, 'status' => LeaveStatus::Pending->value, 'quantity' => $quantity],
                'event' => LeaveTransaction::event('request', $request->id, $request->schoolId, 'submitted', $command->actorUserId, ['status' => LeaveStatus::Pending->value]),
            ];
        });
    }
}
