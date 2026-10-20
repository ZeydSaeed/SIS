<?php

namespace App\Domain\Leave\Repositories;

/** leave.delegations — an approver lets another user decide leave in the school between two dates. */
interface LeaveDelegationRepositoryInterface
{
    public const ACTIVE = 1;

    public const REVOKED = 2;

    /** Users who, today, have delegated their approval to the given user in the school. @return list<int> */
    public function activeDelegatorIds(int $schoolId, int $delegateUserId): array;

    /** Today's active delegations of the school. @return list<array{delegator: int, delegate: int}> */
    public function activePairs(int $schoolId): array;

    /**
     * The school's delegations, newest first (every status), with both people's names.
     *
     * @return list<array<string, mixed>>
     */
    public function list(int $schoolId, ?int $delegatorUserId): array;

    /** @return array<string, mixed>|null */
    public function find(int $schoolId, int $delegationId): ?array;

    /** Whether an active delegation of this delegator to this delegate already overlaps the range. */
    public function overlapsActive(int $schoolId, int $delegatorUserId, int $delegateUserId, string $from, string $to): bool;

    public function create(int $schoolId, int $delegatorUserId, int $delegateUserId, string $from, string $to, int $createdBy): int;

    /** Revokes an active delegation; false = it was not active. */
    public function revoke(int $schoolId, int $delegationId, int $revokedBy): bool;

    /** Whether the user holds an effective role in the school (the only people who may be delegated to). */
    public function hasRoleInSchool(int $schoolId, int $userId): bool;

    /**
     * Users with an effective role in the school, for the delegate picker.
     *
     * @return list<array{id: int, name: string}>
     */
    public function candidates(int $schoolId, int $exceptUserId): array;
}
