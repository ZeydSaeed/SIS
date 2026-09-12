<?php

namespace App\Application\Workflow\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Workflow\Results\CreateApprovalFlowResult;
use App\Domain\Workflow\Events\ApprovalFlowCreated;
use App\Domain\Workflow\Repositories\ApprovalFlowRepositoryInterface;
use App\Domain\Workflow\Support\ApprovalFlowEntityTypes;
use App\Domain\Workflow\Support\ApprovalFlowStepsValidator;
use App\Domain\Workflow\Support\WorkflowIdempotencyGuard;

final class CreateApprovalFlowHandler implements CommandHandler
{
    private const COMMAND_NAME = 'CreateApprovalFlow';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly ApprovalFlowRepositoryInterface $flows,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): CreateApprovalFlowResult
    {
        assert($command instanceof CreateApprovalFlowCommand);
        $key = WorkflowIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return CreateApprovalFlowResult::fromIdempotency((int) $cached['flow_id']);
        }

        $entityType = trim($command->entityType);
        if (! ApprovalFlowEntityTypes::isAllowed($entityType)) {
            return CreateApprovalFlowResult::failure(['workflow.entity_type_invalid']);
        }
        if (trim($command->name) === '') {
            return CreateApprovalFlowResult::failure(['workflow.flow_name_invalid']);
        }

        $stepErrors = ApprovalFlowStepsValidator::validate($command->steps);
        if ($stepErrors !== []) {
            return CreateApprovalFlowResult::failure($stepErrors);
        }

        $normalizedSteps = ApprovalFlowStepsValidator::normalize($command->steps);
        $at = (new \DateTimeImmutable)->format('Y-m-d H:i:s');
        $id = $this->unitOfWork->transaction(function () use ($command, $key, $entityType, $normalizedSteps, $at): int {
            $id = $this->flows->create(
                $command->schoolId,
                $entityType,
                trim($command->name),
                $normalizedSteps,
                $command->isActive,
                $at,
            );
            $this->outbox->stage(new ApprovalFlowCreated(
                $id,
                $command->schoolId,
                $entityType,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, ['flow_id' => $id]);

            return $id;
        });

        return CreateApprovalFlowResult::success($id);
    }
}
