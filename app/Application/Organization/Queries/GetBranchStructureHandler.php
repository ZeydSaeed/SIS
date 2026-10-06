<?php

namespace App\Application\Organization\Queries;

use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Domain\Organization\Repositories\BranchStructureRepositoryInterface;

/** «الفروع والاختصاصات» page: the school's active branches with their active departments. */
final class GetBranchStructureHandler implements QueryHandler
{
    public function __construct(
        private readonly BranchStructureRepositoryInterface $structure,
    ) {}

    /**
     * @return list<array{id:int, code:string, name:string, description:?string, departments: list<array{id:int, code:string, name:string, description:?string}>}>
     */
    public function handle(Query $query): array
    {
        assert($query instanceof GetBranchStructureQuery);

        return $this->structure->structure($query->schoolId, $query->activeOnly);
    }
}
