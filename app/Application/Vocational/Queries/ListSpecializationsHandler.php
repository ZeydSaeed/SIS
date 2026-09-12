<?php

namespace App\Application\Vocational\Queries;

use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Application\Vocational\DTOs\SpecializationDTO;
use App\Application\Vocational\DTOs\SpecializationListPageDTO;
use App\Domain\Vocational\Repositories\VocationalCatalogRepositoryInterface;

final class ListSpecializationsHandler implements QueryHandler
{
    public function __construct(
        private readonly VocationalCatalogRepositoryInterface $catalog,
    ) {}

    public function handle(Query $query): SpecializationListPageDTO
    {
        assert($query instanceof ListSpecializationsQuery);

        $page = max(1, $query->page);
        $perPage = max(1, min(100, $query->perPage));

        $result = $this->catalog->listSpecializations(
            $query->schoolId,
            $query->status,
            $page,
            $perPage,
        );

        $items = [];
        foreach ($result['items'] as $row) {
            $items[] = new SpecializationDTO(
                id: $row->id,
                schoolId: $row->schoolId,
                code: $row->code,
                name: $row->name,
                description: $row->description,
                status: $row->status,
            );
        }

        $totalPages = $perPage > 0 ? (int) ceil($result['total'] / $perPage) : 0;

        return new SpecializationListPageDTO($items, [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $result['total'],
            'total_pages' => $totalPages,
        ]);
    }
}
