<?php

namespace App\Application\Organization\Queries;

use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Application\Organization\Contracts\SchoolRegistryReadRepositoryInterface;
use App\Application\Organization\DTOs\DirectorateRegistryItemDTO;
use App\Application\Organization\DTOs\SchoolRegistryItemDTO;
use App\Domain\Organization\Repositories\BranchStructureRepositoryInterface;

/**
 * «المديريات والمدارس» page: every directorate → the user's schools in it →
 * each school's active branches → their active departments.
 */
final class GetDirectorateSchoolStructureHandler implements QueryHandler
{
    public function __construct(
        private readonly SchoolRegistryReadRepositoryInterface $registry,
        private readonly BranchStructureRepositoryInterface $branches,
    ) {}

    /**
     * @return list<array{
     *     id: int, name: string, region: ?string, status: int,
     *     schools: list<array{id: int, code: string, name: string, address: ?string, phone: ?string, email: ?string, status: int,
     *         branches: list<array{id: int, code: string, name: string, description: ?string, departments: list<array{id: int, code: string, name: string, description: ?string}>}>}>
     * }>
     */
    public function handle(Query $query): array
    {
        assert($query instanceof GetDirectorateSchoolStructureQuery);

        $schools = $this->registry->listSchools($query->allowedSchoolIds);
        $branchesBySchool = $this->branches->structureBySchool(
            array_map(static fn (SchoolRegistryItemDTO $school): int => $school->id, $schools),
        );

        $schoolsByDirectorate = [];
        foreach ($schools as $school) {
            $schoolsByDirectorate[$school->directorateId][] = [
                'id' => $school->id,
                'code' => $school->code,
                'name' => $school->name,
                'address' => $school->address,
                'phone' => $school->phone,
                'email' => $school->email,
                'status' => $school->status,
                'branches' => $branchesBySchool[$school->id] ?? [],
            ];
        }

        return array_map(
            static fn (DirectorateRegistryItemDTO $directorate): array => [
                'id' => $directorate->id,
                'name' => $directorate->name,
                'region' => $directorate->region,
                'status' => $directorate->status,
                'schools' => $schoolsByDirectorate[$directorate->id] ?? [],
            ],
            $this->registry->listDirectorates($query->allowedSchoolIds),
        );
    }
}
