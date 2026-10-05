<?php

namespace App\Application\Organization\Queries;

use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Application\Organization\Contracts\SchoolRegistryReadRepositoryInterface;
use App\Application\Organization\DTOs\DirectorateRegistryItemDTO;
use App\Application\Organization\DTOs\SchoolRegistryItemDTO;

final class ListDirectorateRegistryHandler implements QueryHandler
{
    public function __construct(
        private readonly SchoolRegistryReadRepositoryInterface $registry,
    ) {}

    /**
     * @return array{
     *     directorates: list<array<string, mixed>>,
     *     schools: list<array{id: int, name: string, directorate_id: int}>
     * }
     */
    public function handle(Query $query): array
    {
        assert($query instanceof ListDirectorateRegistryQuery);

        return [
            'directorates' => array_map(
                static fn (DirectorateRegistryItemDTO $item): array => $item->toArray(),
                $this->registry->listDirectorates($query->allowedSchoolIds),
            ),
            // The user's schools — choices for "مدارسك فيها".
            'schools' => array_map(
                static fn (SchoolRegistryItemDTO $school): array => [
                    'id' => $school->id,
                    'name' => $school->name,
                    'directorate_id' => $school->directorateId,
                ],
                $this->registry->listSchools($query->allowedSchoolIds),
            ),
        ];
    }
}
