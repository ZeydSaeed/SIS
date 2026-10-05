<?php

namespace App\Application\Organization\DTOs;

final readonly class DirectorateRegistryItemDTO
{
    /**
     * @param  list<int>  $schoolIds  The user's schools in this directorate.
     */
    public function __construct(
        public int $id,
        public string $name,
        public ?string $region,
        public int $status,
        public array $schoolIds,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'region' => $this->region,
            'status' => $this->status,
            'school_ids' => $this->schoolIds,
            'schools_count' => count($this->schoolIds),
        ];
    }
}
