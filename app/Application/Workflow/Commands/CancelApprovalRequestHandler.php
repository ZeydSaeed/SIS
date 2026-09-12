<?php

namespace App\Application\Workflow\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Workflow\Results\CancelApprovalRequestResult;
use App\Domain\Workflow\Events\ApprovalRequestCancelled;
use App\Domain\Workflow\Repositories\ApprovalRequestRepositoryInterface;
use App\Domain\Workflow\Support\ApprovalCancellationRules;
use App\Domain\Workflow\Support\WorkflowIdempotencyGuard;

final class CancelApprovalRequestHandler implements CommandHandler
{
    private const COMMAND_NAME = 'CancelApprovalRequest';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly ApprovalRequestRepositoryInterface $requests,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): CancelApprovalRequestResult
    {
        assert($command instanceof CancelApprovalRequestCommand);
        $key = WorkflowIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return CancelApprovalRequestResult::fromIdempotency(
                (int) $cached['request_id'],
                (int) $cached['status'],
            );
        }

        $request = $this->requests->findById($command->schoolId, $command->requestId);
        if ($request === null) {
            return CancelApprovalRequestResult::failure(['workflow.approval_request_not_found']);
        }

        $cancelErrors = ApprovalCancellationRules::validateCancellable($request->status);
        if ($cancelErrors !== []) {
            return CancelApprovalRequestResult::failure($cancelErrors);
        }

        $outcome = ApprovalCancellationRules::applyCancel();
        $completedAt = (new \DateTimeImmutable)->format('Y-m-d H:i:s');
        $previousStatus = $request->status;

        $this->unitOfWork->transaction(function () use ($command, $key, $outcome, $completedAt, $previousStatus, $request): void {
            $this->requests->applyDecision(
                $command->schoolId,
                $command->requestId,
                $outcome['status'],
                $request->currentStep,
                $completedAt,
            );
            $this->outbox->stage(new ApprovalRequestCancelled(
                $command->requestId,
                $command->schoolId,
                $previousStatus,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, [
                'request_id' => $command->requestId,
                'status' => $outcome['status'],
            ]);
        });

        return CancelApprovalRequestResult::success($command->requestId, $outcome['status']);
    }
}
