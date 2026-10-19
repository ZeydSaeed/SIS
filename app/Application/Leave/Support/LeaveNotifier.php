<?php

namespace App\Application\Leave\Support;

use App\Domain\Leave\Data\LeaveRequester;
use App\Domain\Leave\Data\LeaveRequestSnapshot;
use App\Domain\Leave\Repositories\LeaveNotificationRepositoryInterface;
use App\Domain\Leave\ValueObjects\LeaveNotificationKind;

/**
 * In-app notifications of the request lifecycle, written inside the handler's transaction (so a notification exists
 * if and only if the change it announces does). Approvers are told when a request waits for them (never about their
 * own leave, never the one who just acted); the requester — and whoever entered the request for them — about the outcome.
 */
final class LeaveNotifier
{
    public function __construct(
        private readonly LeaveNotificationRepositoryInterface $notifications,
    ) {}

    /** @param  list<int>  $except  users never told (already approved a level of this request) */
    public function awaitingApproval(LeaveRequestSnapshot $request, LeaveRequester $requester, int $actorUserId, array $except = []): void
    {
        $skip = [...$except, $actorUserId, ...($requester->userId === null ? [] : [$requester->userId])];
        foreach ($this->notifications->approverUserIds($request->schoolId) as $userId) {
            if (! in_array($userId, $skip, true)) {
                $this->notifications->add($request->schoolId, $userId, $request->id, LeaveNotificationKind::AwaitingApproval, $actorUserId);
            }
        }
    }

    public function outcome(LeaveRequestSnapshot $request, LeaveRequester $requester, LeaveNotificationKind $kind, int $actorUserId): void
    {
        $recipients = array_unique(array_filter([$requester->userId, $request->createdBy], static fn (?int $id): bool => $id !== null));
        foreach ($recipients as $userId) {
            if ($userId !== $actorUserId) {
                $this->notifications->add($request->schoolId, $userId, $request->id, $kind, $actorUserId);
            }
        }
    }
}
