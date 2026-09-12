<?php

namespace App\Infrastructure\Workflow;

use App\Application\Contracts\OutboxRepository;
use App\Application\Workflow\Contracts\ApprovalEntityCompletionHookPort;
use App\Domain\Transfers\Events\TransferRequestApproved;
use App\Domain\Transfers\Events\TransferRequestRejected;
use App\Domain\Transfers\Repositories\TransferRepositoryInterface;
use App\Domain\Transfers\ValueObjects\TransferRequestStatus;
use App\Domain\Workflow\ValueObjects\ApprovalRequestStatus;
use Illuminate\Support\Facades\Log;

final class ApprovalEntityCompletionHookAdapter implements ApprovalEntityCompletionHookPort
{
    private const TRANSFER_ENTITY = 'transfer_request';

    public function __construct(
        private readonly TransferRepositoryInterface $transfers,
        private readonly OutboxRepository $outbox,
    ) {}

    public function onFinalDecision(
        int $approvalSchoolId,
        string $entityType,
        int $entityId,
        int $finalStatus,
        ?int $actorUserId,
    ): void {
        if ($entityType !== self::TRANSFER_ENTITY) {
            return;
        }

        if (! (bool) config('sis.workflow.auto_hooks.transfer_decide_sync', true)) {
            return;
        }

        if (! in_array($finalStatus, [ApprovalRequestStatus::Approved, ApprovalRequestStatus::Rejected], true)) {
            return;
        }

        try {
            $req = $this->transfers->findRequestForSchool($entityId, $approvalSchoolId);
            if ($req === null || $req->status !== TransferRequestStatus::Pending) {
                return;
            }

            $at = (new \DateTimeImmutable)->format('Y-m-d H:i:s');

            if ($finalStatus === ApprovalRequestStatus::Approved) {
                $ok = $this->transfers->markApproved($req->id, $req->toSchoolId, $actorUserId, $at);
                if (! $ok) {
                    return;
                }
                $this->outbox->stage(new TransferRequestApproved(
                    $req->id,
                    $req->fromSchoolId,
                    $req->toSchoolId,
                    new \DateTimeImmutable,
                ));

                return;
            }

            $ok = $this->transfers->markRejected($req->id, $req->toSchoolId, $actorUserId, $at);
            if (! $ok) {
                return;
            }
            $this->outbox->stage(new TransferRequestRejected(
                $req->id,
                $req->fromSchoolId,
                $req->toSchoolId,
                new \DateTimeImmutable,
            ));
        } catch (\Throwable $e) {
            Log::warning('workflow.auto_hook.transfer_decide_sync_failed', [
                'approval_school_id' => $approvalSchoolId,
                'entity_id' => $entityId,
                'final_status' => $finalStatus,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
