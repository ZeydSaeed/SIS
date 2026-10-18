<?php

namespace App\Domain\Leave\Repositories;

use App\Domain\Leave\Data\LeaveDraft;
use App\Domain\Leave\Data\LeaveRequester;
use App\Domain\Leave\Data\LeaveRequestSnapshot;
use App\Domain\Leave\ValueObjects\LeaveEventType;
use App\Domain\Leave\ValueObjects\LeaveStatus;
use App\Domain\Leave\ValueObjects\LeaveUnit;
use App\Domain\Leave\ValueObjects\RequesterKind;

/** leave.requests + their append-only leave.request_events. */
interface LeaveRequestRepositoryInterface
{
    public function find(int $schoolId, int $requestId): ?LeaveRequestSnapshot;

    /** SELECT … FOR UPDATE — call inside the handler's transaction. */
    public function lock(int $schoolId, int $requestId): ?LeaveRequestSnapshot;

    /** The next request number of the school (serialised by an advisory lock — call inside the transaction). */
    public function nextNumber(int $schoolId): int;

    public function create(
        int $schoolId,
        int $academicYearId,
        int $requestNo,
        LeaveRequester $requester,
        int $policyId,
        LeaveDraft $draft,
        float $quantity,
        LeaveUnit $unit,
        int $approvalLevels,
        int $createdBy,
    ): int;

    /** Rewrites an editable request when its version still matches; false = changed meanwhile. */
    public function updateDraft(int $requestId, int $version, int $policyId, LeaveDraft $draft, float $quantity, LeaveUnit $unit, int $approvalLevels): bool;

    /**
     * Moves the request to a new status when its version still matches (version + 1); false = changed meanwhile.
     * `$submitted` stamps submitted_at; `$decidedBy` stamps the decision.
     */
    public function transition(int $requestId, int $version, LeaveStatus $to, int $approvedLevels, ?int $decidedBy, ?string $note, bool $submitted = false): bool;

    /**
     * Serialises the submissions of one person (transaction-scoped advisory lock), so two overlapping requests
     * submitted at the same moment cannot both pass the overlap check.
     */
    public function lockPerson(int $schoolId, RequesterKind $kind, int $personId): void;

    /** Whether the person already has a pending, returned or approved request overlapping the range. */
    public function overlaps(int $schoolId, RequesterKind $kind, int $personId, string $from, string $to, ?int $exceptId): bool;

    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function addEvent(int $schoolId, int $requestId, LeaveEventType $type, ?LeaveStatus $from, LeaveStatus $to, ?int $level, ?int $actorUserId, ?string $note, ?array $payload = null): void;

    /** @return list<int> users who approved a level since the request was last submitted */
    public function currentApprovers(int $requestId): array;
}
