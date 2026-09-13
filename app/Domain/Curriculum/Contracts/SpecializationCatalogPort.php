<?php

namespace App\Domain\Curriculum\Contracts;

/**
 * Read-only vocational specialization views for curriculum binding (CUR-U07).
 */
interface SpecializationCatalogPort
{
    public function activeInSchool(int $schoolId, int $specializationId): bool;
}
