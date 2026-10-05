<?php

namespace App\Application\Organization\Contracts;

use App\Application\Organization\DTOs\DirectorateRegistryItemDTO;
use App\Application\Organization\DTOs\SchoolRegistryItemDTO;

interface SchoolRegistryReadRepositoryInterface
{
    /**
     * @param  list<int>  $schoolIds
     * @return list<SchoolRegistryItemDTO>
     */
    public function listSchools(array $schoolIds): array;

    /**
     * @return list<array{id: int, name: string}>
     */
    public function listActiveDirectorates(): array;

    /**
     * All directorates with the number of the given schools in each.
     *
     * @param  list<int>  $schoolIds
     * @return list<DirectorateRegistryItemDTO>
     */
    public function listDirectorates(array $schoolIds): array;
}
