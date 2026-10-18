<?php

namespace App\Application\Leave\Queries;

use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Application\Leave\Contracts\LeaveReadRepositoryInterface;
use App\Application\Leave\Support\LeavePrivacy;

/** «طلبات الإجازات»: the school's requests (or only the viewer's own), paged and filtered in SQL, masked. */
final class ListLeaveRequestsHandler implements QueryHandler
{
    public function __construct(
        private readonly LeaveReadRepositoryInterface $read,
    ) {}

    /** @return array{items: list<array<string, mixed>>, total: int} */
    public function handle(Query $query): array
    {
        assert($query instanceof ListLeaveRequestsQuery);
        // A viewer without «عرض الإجازات» is always limited to their own; anyone may ask for only their own («طلباتي»).
        $filters = $query->filters;
        $filters['own_user_id'] = $query->viewer->ownScope() ?? ($filters['own_user_id'] ?? null);
        $list = $this->read->requests($query->schoolId, $filters, $query->page, $query->perPage);
        $list['items'] = array_map(static fn (array $row): array => LeavePrivacy::mask($row, $query->viewer), $list['items']);

        return $list;
    }
}
