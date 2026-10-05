<?php

namespace App\Application\Organization\DTOs;

final readonly class SchoolRegistryItemDTO
{
    public function __construct(
        public int $id,
        public int $directorateId,
        public ?string $directorateName,
        public string $code,
        public string $name,
        public ?string $address,
        public ?string $phone,
        public ?string $email,
        public int $status,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'directorate_id' => $this->directorateId,
            'directorate_name' => $this->directorateName,
            'code' => $this->code,
            'name' => $this->name,
            'address' => $this->address,
            'phone' => $this->phone,
            'email' => $this->email,
            'status' => $this->status,
        ];
    }
}
