<?php

namespace App\Application\Leave\Queries;

use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Domain\Leave\Repositories\LeaveDelegationRepositoryInterface;

/** «التفويض»: the delegations the viewer may see, and the people they can delegate to. */
final class ListLeaveDelegationsHandler implements QueryHandler
{
    public function __construct(
        private readonly LeaveDelegationRepositoryInterface $delegations,
    ) {}

    /** @return array{items: list<array<string, mixed>>, candidates: list<array{id: int, name: string}>} */
    public function handle(Query $query): array
    {
        assert($query instanceof ListLeaveDelegationsQuery);

        return [
            'items' => $this->delegations->list($query->schoolId, $query->delegatorUserId),
            'candidates' => $this->delegations->candidates($query->schoolId, $query->userId),
        ];
    }
}
