<?php

namespace App\Application\Leave\Contracts;

use App\Application\Leave\Support\LeavePrivacy;

/**
 * The read side of the leave module (lists, details, dashboard, balances, calendar, reports) — joined, paged and
 * filtered in SQL; school-bounded by the caller and by RLS. Rows carry is_sensitive / requester_user_id /
 * created_by so the Application layer can mask them ({@see LeavePrivacy}).
 */
interface LeaveReadRepositoryInterface
{
    /**
     * @param  array{year_id?: int|null, statuses?: list<int>, kind?: int|null, type_ids?: list<int>, q?: string, from?: string|null, to?: string|null, own_user_id?: int|null, approver_user_id?: int|null, sort?: string, direction?: string}  $filters
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function requests(int $schoolId, array $filters, int $page, int $perPage): array;

    /** How many pending requests the user may still decide («بانتظار موافقتي»). */
    public function awaitingCount(int $schoolId, int $userId): int;

    /** @return array<string, mixed>|null */
    public function request(int $schoolId, int $requestId): ?array;

    /** @return list<array<string, mixed>> oldest first */
    public function events(int $schoolId, int $requestId): array;

    /**
     * @return array<string, int>
     */
    public function dashboardCounts(int $schoolId, int $academicYearId, string $today, ?int $ownUserId): array;

    /**
     * Approved requests covering a date (who is away).
     *
     * @return list<array<string, mixed>>
     */
    public function awayOn(int $schoolId, string $date, ?int $ownUserId): array;

    /**
     * Approved and open requests starting in [from, to] (what is coming).
     *
     * @return list<array<string, mixed>>
     */
    public function startingBetween(int $schoolId, string $from, string $to, ?int $ownUserId): array;

    /**
     * Person × type balances of the year (ledger sums + what open requests hold back).
     *
     * @param  array{kind?: int|null, type_ids?: list<int>, q?: string, people?: list<array{kind: int, id: int}>}  $filters
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function balances(int $schoolId, int $academicYearId, array $filters, int $page, int $perPage): array;

    /** @return list<array<string, mixed>> oldest first */
    public function ledger(int $schoolId, int $academicYearId, int $kind, int $personId, ?int $typeId): array;

    /**
     * Pending, returned and approved requests overlapping [from, to].
     *
     * @param  array{kind?: int|null, type_ids?: list<int>, statuses?: list<int>}  $filters
     * @return list<array<string, mixed>>
     */
    public function calendar(int $schoolId, string $from, string $to, array $filters, ?int $ownUserId): array;

    /**
     * Students' approved and pending absence excuses overlapping [from, to] (attendance.excuses — read only).
     *
     * @return list<array<string, mixed>>
     */
    public function studentExcuses(int $schoolId, string $from, string $to): array;

    /**
     * @param  array{from?: string|null, to?: string|null, kind?: int|null, type_ids?: list<int>}  $filters
     * @return list<array<string, mixed>>
     */
    public function report(int $schoolId, int $academicYearId, string $kind, array $filters): array;
}
