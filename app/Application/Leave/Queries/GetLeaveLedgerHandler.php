<?php

namespace App\Application\Leave\Queries;

use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Application\Leave\Contracts\LeaveReadRepositoryInterface;
use App\Application\Leave\Contracts\LeaveStaffDirectoryInterface;
use App\Domain\Leave\Data\LeaveRequester;

/** «كشف حركة الرصيد»: every ledger row of a person (one type or all) — null when the viewer may not see it. */
final class GetLeaveLedgerHandler implements QueryHandler
{
    public function __construct(
        private readonly LeaveReadRepositoryInterface $read,
        private readonly LeaveStaffDirectoryInterface $staff,
    ) {}

    /** @return list<array<string, mixed>>|null */
    public function handle(Query $query): ?array
    {
        assert($query instanceof GetLeaveLedgerQuery);
        if (! $query->viewer->seesBalances) {
            $own = array_filter(
                $this->staff->ownRequesters($query->viewer->userId, $query->schoolId, $query->academicYearId),
                static fn (LeaveRequester $r): bool => $r->kind === $query->kind && $r->personId === $query->personId,
            );
            if ($own === []) {
                return null;
            }
        }

        return $this->read->ledger($query->schoolId, $query->academicYearId, $query->kind->value, $query->personId, $query->typeId);
    }
}
