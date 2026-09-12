<?php

namespace App\Application\Workflow\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Workflow\Results\CreateApprovalRequestResult;
use App\Domain\Workflow\Events\ApprovalRequestCreated;
use App\Domain\Workflow\Repositories\ApprovalFlowRepositoryInterface;
use App\Domain\Workflow\Repositories\ApprovalRequestRepositoryInterface;
use App\Domain\Workflow\Support\ApprovalFlowEntityTypes;
use App\Domain\Workflow\Support\WorkflowIdempotencyGuard;
use App\Domain\Workflow\ValueObjects\ApprovalRequestStatus;

final class CreateApprovalRequestHandler implements CommandHandler
{
    private const COMMAND_NAME = 'CreateApprovalRequest';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly ApprovalFlowRepositoryInterface $flows,
        private readonly ApprovalRequestRepositoryInterface $requests,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): CreateApprovalRequestResult
    {
        assert($command instanceof CreateApprovalRequestCommand);
        $key = WorkflowIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return CreateApprovalRequestResult::fromIdempotency((int) $cached['request_id']);
        }

        $entityType = trim($command->entityType);
        if (! ApprovalFlowEntityTypes::isAllowed($entityType) || $command->entityId < 1) {
            return CreateApprovalRequestResult::failure(['workflow.entity_invalid']);
        }

        $flow = $this->flows->findById($command->schoolId, $command->flowId);
        if ($flow === null || ! $flow->isActive) {
            return CreateApprovalRequestResult::failure(['workflow.flow_not_found']);
        }
        if ($flow->entityType !== $entityType) {
            return CreateApprovalRequestResult::failure(['workflow.entity_type_mismatch']);
        }
        if ($this->requests->hasOpenForEntity($command->schoolId, $entityType, $command->entityId)) {
            return CreateApprovalRequestResult::failure(['workflow.approval_request_open_exists']);
        }

        $at = (new \DateTimeImmutable)->format('Y-m-d H:i:s');
        $id = $this->unitOfWork->transaction(function () use ($command, $key, $entityType, $at): int {
            $id = $this->requests->create(
                $command->schoolId,
                $command->flowId,
                $entityType,
                $command->entityId,
                1,
                ApprovalRequestStatus::Pending,
                $command->requestedBy,
                $at,
            );
            $this->outbox->stage(new ApprovalRequestCreated(
                $id,
                $command->schoolId,
                $command->flowId,
                $entityType,
                $command->entityId,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, ['request_id' => $id]);

            return $id;
        });

        return CreateApprovalRequestResult::success($id);
    }
}
