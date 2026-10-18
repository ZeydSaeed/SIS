<?php

namespace App\Domain\Leave\Repositories;

use App\Domain\Leave\Data\LeaveBalance;
use App\Domain\Leave\ValueObjects\BalanceEntryType;
use App\Domain\Leave\ValueObjects\RequesterKind;

/** leave.balance_entries — the append-only ledger; balances are always summed from it. */
interface LeaveBalanceRepositoryInterface
{
    /**
     * Serialises every balance write of one person · type · year (transaction-scoped advisory lock), so two
     * concurrent approvals or submissions cannot both spend the same balance.
     */
    public function lockPerson(int $schoolId, int $academicYearId, int $typeId, RequesterKind $kind, int $personId): void;

    /** `$exceptRequestId`: a request whose own reservation is left out (it is the one being decided). */
    public function balance(int $schoolId, int $academicYearId, int $typeId, RequesterKind $kind, int $personId, ?int $exceptRequestId = null): LeaveBalance;

    public function addEntry(
        int $schoolId,
        int $academicYearId,
        int $typeId,
        RequesterKind $kind,
        int $personId,
        BalanceEntryType $type,
        float $quantity,
        ?int $requestId,
        ?string $reason,
        ?int $createdBy,
    ): int;

    public function hasRequestEntry(int $requestId, BalanceEntryType $type): bool;

    /** The deduction a request made (positive), or null when it deducted nothing. */
    public function deductedFor(int $requestId): ?float;

    /**
     * One entitlement row per person that has none yet for the type and year (existing ones are left untouched).
     *
     * @param  list<array{kind: RequesterKind, person_id: int}>  $people
     * @return int rows added
     */
    public function grantEntitlements(int $schoolId, int $academicYearId, int $typeId, float $quantity, array $people, ?int $createdBy): int;
}
