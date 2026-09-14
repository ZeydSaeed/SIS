<?php

namespace App\Application\Workflow\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Workflow\Results\ReopenApprovalRequestResult;
use App\Domain\Workflow\Events\ApprovalRequestReopened;
use App\Domain\Workflow\Repositories\ApprovalRequestRepositoryInterface;
use App\Domain\Workflow\Support\WorkflowIdempotencyGuard;
use App\Domain\Workflow\ValueObjects\ApprovalRequestStatus;

final class ReopenApprovalRequestHandler implements CommandHandler
{
    private const COMMAND_NAME = 'ReopenApprovalRequest';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly ApprovalRequestRepositoryInterface $requests,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): ReopenApprovalRequestResult
    {
        assert($command instanceof ReopenApprovalRequestCommand);
        $key = WorkflowIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return ReopenApprovalRequestResult::fromIdempotency(
                (int) $cached['request_id'],
                (int) $cached['status'],
            );
        }

        $request = $this->requests->findById($command->schoolId, $command->requestId);
        if ($request === null) {
            return ReopenApprovalRequestResult::failure(['workflow.approval_request_not_found']);
        }
        if ($request->status === ApprovalRequestStatus::Pending) {
            return ReopenApprovalRequestResult::failure(['workflow.approval_request_already_open']);
        }
        if ($request->status !== ApprovalRequestStatus::Cancelled) {
            return ReopenApprovalRequestResult::failure(['workflow.approval_request_not_reopenable']);
        }

        $previousStatus = $request->status;

        $ok = $this->unitOfWork->transaction(function () use ($command, $key, $previousStatus): bool {
            if (! $this->requests->markReopened($command->schoolId, $command->requestId)) {
                return false;
            }

            $this->outbox->stage(new ApprovalRequestReopened(
                $command->requestId,
                $command->schoolId,
                $previousStatus,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, [
                'request_id' => $command->requestId,
                'status' => ApprovalRequestStatus::Pending,
            ]);

            return true;
        });

        if (! $ok) {
            return ReopenApprovalRequestResult::failure(['workflow.approval_request_reopen_failed']);
        }

        return ReopenApprovalRequestResult::success($command->requestId, ApprovalRequestStatus::Pending);
    }
}
