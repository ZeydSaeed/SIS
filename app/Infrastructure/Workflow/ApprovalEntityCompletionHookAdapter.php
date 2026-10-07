<?php

namespace App\Infrastructure\Workflow;

use App\Application\Contracts\OutboxRepository;
use App\Application\Workflow\Contracts\ApprovalEntityCompletionHookPort;
use App\Domain\Timetable\Events\TimetableEngineChanged;
use App\Domain\Timetable\Repositories\TimetableVersionRepositoryInterface;
use App\Domain\Timetable\ValueObjects\TimetableVersionStatus;
use App\Domain\Transfers\Events\TransferRequestApproved;
use App\Domain\Transfers\Events\TransferRequestRejected;
use App\Domain\Transfers\Repositories\TransferRepositoryInterface;
use App\Domain\Transfers\ValueObjects\TransferRequestStatus;
use App\Domain\Workflow\ValueObjects\ApprovalRequestStatus;
use Illuminate\Support\Facades\Log;

final class ApprovalEntityCompletionHookAdapter implements ApprovalEntityCompletionHookPort
{
    private const TRANSFER_ENTITY = 'transfer_request';

    private const TIMETABLE_VERSION_ENTITY = 'timetable_version';

    public function __construct(
        private readonly TransferRepositoryInterface $transfers,
        private readonly OutboxRepository $outbox,
        private readonly TimetableVersionRepositoryInterface $timetableVersions,
    ) {}

    public function onFinalDecision(
        int $approvalSchoolId,
        string $entityType,
        int $entityId,
        int $finalStatus,
        ?int $actorUserId,
    ): void {
        if ($entityType === self::TIMETABLE_VERSION_ENTITY) {
            $this->decideTimetableVersion($approvalSchoolId, $entityId, $finalStatus, $actorUserId);

            return;
        }

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

    /**
     * Timetable version in review → approved / rejected with the decision's actor and time (same transaction as
     * the workflow decision — a version never shows approved without its approved request).
     */
    private function decideTimetableVersion(int $schoolId, int $versionId, int $finalStatus, ?int $actorUserId): void
    {
        $to = match ($finalStatus) {
            ApprovalRequestStatus::Approved => TimetableVersionStatus::Approved,
            ApprovalRequestStatus::Rejected => TimetableVersionStatus::Rejected,
            default => null,
        };
        if ($to === null) {
            return;
        }
        $version = $this->timetableVersions->find($schoolId, $versionId);
        if ($version === null) {
            return;
        }
        $changed = $this->timetableVersions->setStatus($schoolId, $versionId, TimetableVersionStatus::Review->value, $to->value, [
            'decided_by' => $actorUserId,
            'decided_at' => (new \DateTimeImmutable)->format('Y-m-d H:i:sP'),
        ]);
        if ($changed) {
            $this->outbox->stage(new TimetableEngineChanged('version_decided', $schoolId, $version['academic_year_id'], $versionId, $actorUserId,
                ['status' => $to->value], new \DateTimeImmutable));
        }
    }
}
