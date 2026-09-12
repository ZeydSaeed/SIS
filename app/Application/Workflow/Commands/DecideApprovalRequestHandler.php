<?php

namespace App\Application\Workflow\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Workflow\Contracts\ActorSchoolRolesPort;
use App\Application\Workflow\Contracts\ApprovalEntityCompletionHookPort;
use App\Application\Workflow\Results\DecideApprovalRequestResult;
use App\Domain\Workflow\Events\ApprovalRequestDecided;
use App\Domain\Workflow\Repositories\ApprovalFlowRepositoryInterface;
use App\Domain\Workflow\Repositories\ApprovalRequestRepositoryInterface;
use App\Domain\Workflow\Support\ApprovalDecisionRules;
use App\Domain\Workflow\Support\WorkflowIdempotencyGuard;

final class DecideApprovalRequestHandler implements CommandHandler
{
    private const COMMAND_NAME = 'DecideApprovalRequest';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly ApprovalRequestRepositoryInterface $requests,
        private readonly ApprovalFlowRepositoryInterface $flows,
        private readonly ActorSchoolRolesPort $actorRoles,
        private readonly ApprovalEntityCompletionHookPort $completionHook,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): DecideApprovalRequestResult
    {
        assert($command instanceof DecideApprovalRequestCommand);
        $key = WorkflowIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return DecideApprovalRequestResult::fromIdempotency(
                (int) $cached['request_id'],
                (int) $cached['status'],
                (int) $cached['current_step'],
            );
        }

        $decisionErrors = ApprovalDecisionRules::validateDecision($command->decision);
        if ($decisionErrors !== []) {
            return DecideApprovalRequestResult::failure($decisionErrors);
        }

        $request = $this->requests->findById($command->schoolId, $command->requestId);
        if ($request === null) {
            return DecideApprovalRequestResult::failure(['workflow.approval_request_not_found']);
        }

        $pendingErrors = ApprovalDecisionRules::validatePending($request->status);
        if ($pendingErrors !== []) {
            return DecideApprovalRequestResult::failure($pendingErrors);
        }

        $flow = $this->flows->findById($command->schoolId, $request->flowId);
        if ($flow === null) {
            return DecideApprovalRequestResult::failure(['workflow.flow_not_found']);
        }

        $requiredRole = ApprovalDecisionRules::roleForStep($flow->steps, $request->currentStep);
        $roleErrors = ApprovalDecisionRules::validateActorRole(
            $requiredRole,
            $this->actorRoles->roleCodesForUserInSchool($command->actorUserId, $command->schoolId),
        );
        if ($roleErrors !== []) {
            return DecideApprovalRequestResult::failure($roleErrors);
        }

        $maxStep = ApprovalDecisionRules::maxStep($flow->steps);
        $outcome = $command->decision === ApprovalDecisionRules::Approve
            ? ApprovalDecisionRules::applyApprove($request->currentStep, $maxStep)
            : ApprovalDecisionRules::applyReject($request->currentStep);

        $completedAt = $outcome['completed']
            ? (new \DateTimeImmutable)->format('Y-m-d H:i:s')
            : null;

        $entityType = $request->entityType;
        $entityId = $request->entityId;

        $this->unitOfWork->transaction(function () use (
            $command,
            $key,
            $outcome,
            $completedAt,
            $entityType,
            $entityId,
        ): void {
            $this->requests->applyDecision(
                $command->schoolId,
                $command->requestId,
                $outcome['status'],
                $outcome['current_step'],
                $completedAt,
            );
            $this->outbox->stage(new ApprovalRequestDecided(
                $command->requestId,
                $command->schoolId,
                $command->decision,
                $outcome['status'],
                $outcome['current_step'],
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, [
                'request_id' => $command->requestId,
                'status' => $outcome['status'],
                'current_step' => $outcome['current_step'],
            ]);

            if ($outcome['completed']) {
                $this->completionHook->onFinalDecision(
                    $command->schoolId,
                    $entityType,
                    $entityId,
                    $outcome['status'],
                    $command->actorUserId,
                );
            }
        });

        return DecideApprovalRequestResult::success(
            $command->requestId,
            $outcome['status'],
            $outcome['current_step'],
        );
    }
}
