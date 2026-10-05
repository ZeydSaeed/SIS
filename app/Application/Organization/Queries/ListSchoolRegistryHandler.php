<?php

namespace App\Application\Organization\Queries;

use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Application\Organization\Contracts\SchoolRegistryReadRepositoryInterface;
use App\Application\Organization\DTOs\SchoolRegistryDTO;

final class ListSchoolRegistryHandler implements QueryHandler
{
    public function __construct(
        private readonly SchoolRegistryReadRepositoryInterface $registry,
    ) {}

    public function handle(Query $query): SchoolRegistryDTO
    {
        assert($query instanceof ListSchoolRegistryQuery);

        return new SchoolRegistryDTO(
            schools: $this->registry->listSchools($query->allowedSchoolIds),
            directorates: $this->registry->listActiveDirectorates(),
        );
    }
}
