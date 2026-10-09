<?php

namespace App\Application\Teachers\Queries;

use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Domain\Teachers\Repositories\AcademicTitleRepositoryInterface;

final class ListAcademicTitlesHandler implements QueryHandler
{
    public function __construct(
        private readonly AcademicTitleRepositoryInterface $titles,
    ) {}

    /** @return list<array{id: int, code: string, name: string, abbreviation: string|null}> */
    public function handle(Query $query): array
    {
        assert($query instanceof ListAcademicTitlesQuery);

        return $this->titles->active();
    }
}
