<?php

namespace App\Application\Leave\Queries;

use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Application\Leave\Contracts\LeaveReadRepositoryInterface;
use App\Application\Leave\Contracts\LeaveStaffDirectoryInterface;
use App\Domain\Leave\Data\LeaveRequester;

/**
 * «أرصدة الإجازات»: granted · adjusted · used · held by open requests · available, per person and type for the year.
 * Without «عرض الأرصدة» a user sees only their own rows.
 */
final class ListLeaveBalancesHandler implements QueryHandler
{
    public function __construct(
        private readonly LeaveReadRepositoryInterface $read,
        private readonly LeaveStaffDirectoryInterface $staff,
    ) {}

    /** @return array{items: list<array<string, mixed>>, total: int} */
    public function handle(Query $query): array
    {
        assert($query instanceof ListLeaveBalancesQuery);
        $filters = $query->filters;
        if (! $query->viewer->seesBalances) {
            $own = $this->staff->ownRequesters($query->viewer->userId, $query->schoolId, $query->academicYearId);
            if ($own === []) {
                return ['items' => [], 'total' => 0];
            }
            $filters['people'] = array_map(static fn (LeaveRequester $r): array => ['kind' => $r->kind->value, 'id' => $r->personId], $own);
        }

        return $this->read->balances($query->schoolId, $query->academicYearId, $filters, $query->page, $query->perPage);
    }
}
