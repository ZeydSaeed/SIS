<?php

namespace App\Application\Organization\DTOs;

final readonly class SchoolRegistryDTO
{
    /**
     * @param  list<SchoolRegistryItemDTO>  $schools
     * @param  list<array{id: int, name: string}>  $directorates
     */
    public function __construct(
        public array $schools,
        public array $directorates,
    ) {}

    /** @return array{schools: list<array<string, mixed>>, directorates: list<array{id: int, name: string}>} */
    public function toArray(): array
    {
        return [
            'schools' => array_map(
                static fn (SchoolRegistryItemDTO $school): array => $school->toArray(),
                $this->schools,
            ),
            'directorates' => $this->directorates,
        ];
    }
}
