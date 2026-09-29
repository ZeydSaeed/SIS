<?php

namespace App\Domain\Curriculum\Contracts;

use App\Domain\Curriculum\Data\SpecializationSubjectTemplate;

/**
 * Read-only vocational specialization views for curriculum binding (CUR-U07).
 */
interface SpecializationCatalogPort
{
    public function activeInSchool(int $schoolId, int $specializationId): bool;

    /**
     * Active specialization_subjects templates for a school specialization.
     *
     * @return list<SpecializationSubjectTemplate>
     */
    public function listActiveSubjectTemplates(int $schoolId, int $specializationId): array;
}
