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

    /** @param  list<int>  $requestIds  open requests a holiday change may have made stale */
    public function holidayChanged(int $schoolId, array $requestIds): int
    {
        $told = 0;
        foreach ($this->notifications->managerUserIds($schoolId) as $userId) {
            foreach ($requestIds as $requestId) {
                $this->notifications->add($schoolId, $userId, $requestId, LeaveNotificationKind::HolidayChanged, null);
                $told++;
            }
        }

        return $told;
    }

    /**
     * Once per round and approver: the request has waited too long. Returns how many people were told.
     *
     * @param  list<int>  $except  users who already approved a level of this round
     */
    public function reminder(LeaveRequestSnapshot $request, LeaveRequester $requester, string $submittedAt, array $except): int
    {
        $skip = [...$except, ...($requester->userId === null ? [] : [$requester->userId])];

        return $this->once($request, $this->notifications->approverUserIds($request->schoolId), $skip, LeaveNotificationKind::Reminder, $submittedAt);
    }

    /** Once per round and manager: still undecided after the escalation period. Returns how many people were told. */
    public function escalation(LeaveRequestSnapshot $request, LeaveRequester $requester, string $submittedAt): int
    {
        $skip = $requester->userId === null ? [] : [$requester->userId];

        return $this->once($request, $this->notifications->managerUserIds($request->schoolId), $skip, LeaveNotificationKind::Escalated, $submittedAt);
    }

    /**
     * @param  list<int>  $users
     * @param  list<int>  $skip
     */
    private function once(LeaveRequestSnapshot $request, array $users, array $skip, LeaveNotificationKind $kind, string $since): int
    {
        $told = 0;
        foreach ($users as $userId) {
            if (in_array($userId, $skip, true) || $this->notifications->exists($userId, $request->id, $kind, $since)) {
                continue;
            }
            $this->notifications->add($request->schoolId, $userId, $request->id, $kind, null);
            $told++;
        }

        return $told;
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
