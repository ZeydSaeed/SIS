<?php

namespace App\Infrastructure\Workflow;

use App\Application\Contracts\OutboxRepository;
use App\Application\Transfers\Contracts\TransferApprovalHookPort;
use App\Domain\Workflow\Events\ApprovalRequestCreated;
use App\Domain\Workflow\Repositories\ApprovalFlowRepositoryInterface;
use App\Domain\Workflow\Repositories\ApprovalRequestRepositoryInterface;
use App\Domain\Workflow\Support\ApprovalFlowEntityTypes;
use App\Domain\Workflow\ValueObjects\ApprovalRequestStatus;
use Illuminate\Support\Facades\Log;

final class TransferApprovalHookAdapter implements TransferApprovalHookPort
{
    private const ENTITY_TYPE = 'transfer_request';

    public function __construct(
        private readonly ApprovalFlowRepositoryInterface $flows,
        private readonly ApprovalRequestRepositoryInterface $requests,
        private readonly OutboxRepository $outbox,
    ) {}

    public function openForNewTransferRequest(
        int $fromSchoolId,
        int $transferRequestId,
        ?int $requestedBy,
    ): void {
        if (! (bool) config('sis.workflow.auto_hooks.transfer_request', true)) {
            return;
        }

        if ($transferRequestId < 1 || ! ApprovalFlowEntityTypes::isAllowed(self::ENTITY_TYPE)) {
            return;
        }

        try {
            if ($this->requests->hasOpenForEntity($fromSchoolId, self::ENTITY_TYPE, $transferRequestId)) {
                return;
            }

            $flow = $this->flows->findActiveByEntityType($fromSchoolId, self::ENTITY_TYPE);
            if ($flow === null) {
                return;
            }

            $at = (new \DateTimeImmutable)->format('Y-m-d H:i:s');
            $approvalId = $this->requests->create(
                $fromSchoolId,
                $flow->id,
                self::ENTITY_TYPE,
                $transferRequestId,
                1,
                ApprovalRequestStatus::Pending,
                $requestedBy,
                $at,
            );

            $this->outbox->stage(new ApprovalRequestCreated(
                $approvalId,
                $fromSchoolId,
                $flow->id,
                self::ENTITY_TYPE,
                $transferRequestId,
                new \DateTimeImmutable,
            ));
        } catch (\Throwable $e) {
            Log::warning('workflow.auto_hook.transfer_request_failed', [
                'from_school_id' => $fromSchoolId,
                'transfer_request_id' => $transferRequestId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
