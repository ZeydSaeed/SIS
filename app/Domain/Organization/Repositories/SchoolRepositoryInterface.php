<?php

namespace App\Domain\Organization\Repositories;

use App\Domain\Organization\Data\SchoolSnapshot;

interface SchoolRepositoryInterface
{
    public function find(int $schoolId): ?SchoolSnapshot;

    /** Next free system code (codes are generated, never typed by users). */
    public function nextCode(): string;

    public function directorateIsActive(int $directorateId): bool;

    public function create(
        int $directorateId,
        string $code,
        string $name,
        int $schoolType,
        ?string $address,
        ?string $phone,
        ?string $email,
        string $createdAt,
    ): int;

    /**
     * @param  array{directorate_id?: int, name?: string, address?: ?string, phone?: ?string, email?: ?string}  $fields
     */
    public function update(int $schoolId, array $fields, string $updatedAt): bool;

    public function setStatus(int $schoolId, int $status, string $updatedAt): bool;
}
