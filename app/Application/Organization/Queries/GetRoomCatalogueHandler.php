<?php

namespace App\Application\Organization\Queries;

use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Application\Organization\DTOs\RoomCatalogueDTO;
use App\Domain\Organization\Repositories\BranchStructureRepositoryInterface;
use App\Domain\Organization\Repositories\RoomCatalogueRepositoryInterface;

final class GetRoomCatalogueHandler implements QueryHandler
{
    public function __construct(
        private readonly RoomCatalogueRepositoryInterface $rooms,
        private readonly BranchStructureRepositoryInterface $structure,
    ) {}

    public function handle(Query $query): RoomCatalogueDTO
    {
        assert($query instanceof GetRoomCatalogueQuery);
        $perPage = max(5, min(100, $query->perPage));
        $page = $this->rooms->page($query->schoolId, $query->filters, $query->sort, $query->direction, $query->page, $perPage);
        $lastPage = max(1, (int) ceil($page['total'] / $perPage));

        return new RoomCatalogueDTO(
            rooms: array_map(fn (array $r): array => $r + [
                'abbreviation_suggested' => $r['room_number'] ?? $r['code'],
            ], $page['rows']),
            types: $this->rooms->types($query->schoolId),
            branches: array_map(static fn (array $b): array => [
                'id' => $b['id'],
                'name' => $b['name'],
                'departments' => array_map(static fn (array $d): array => ['id' => $d['id'], 'name' => $d['name']], $b['departments']),
            ], $this->structure->structure($query->schoolId)),
            stats: $this->rooms->stats($query->schoolId),
            pagination: ['page' => min(max(1, $query->page), $lastPage), 'per_page' => $perPage, 'total' => $page['total'], 'last_page' => $lastPage],
        );
    }
}
