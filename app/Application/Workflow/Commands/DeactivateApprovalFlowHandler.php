<?php

namespace App\Application\Workflow\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Workflow\Results\DeactivateApprovalFlowResult;
use App\Domain\Workflow\Events\ApprovalFlowDeactivated;
use App\Domain\Workflow\Repositories\ApprovalFlowRepositoryInterface;
use App\Domain\Workflow\Support\WorkflowIdempotencyGuard;

final class DeactivateApprovalFlowHandler implements CommandHandler
{
    private const COMMAND_NAME = 'DeactivateApprovalFlow';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly ApprovalFlowRepositoryInterface $flows,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): DeactivateApprovalFlowResult
    {
        assert($command instanceof DeactivateApprovalFlowCommand);
        $key = WorkflowIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return DeactivateApprovalFlowResult::fromIdempotency((int) $cached['flow_id']);
        }

        $row = $this->flows->findById($command->schoolId, $command->flowId);
        if ($row === null) {
            return DeactivateApprovalFlowResult::failure(['workflow.flow_not_found']);
        }
        if (! $row->isActive) {
            return DeactivateApprovalFlowResult::failure(['workflow.flow_already_inactive']);
        }

        $this->unitOfWork->transaction(function () use ($command, $key): void {
            $this->flows->setActive($command->schoolId, $command->flowId, false);
            $this->outbox->stage(new ApprovalFlowDeactivated(
                $command->flowId,
                $command->schoolId,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, ['flow_id' => $command->flowId]);
        });

        return DeactivateApprovalFlowResult::success($command->flowId);
    }
}
