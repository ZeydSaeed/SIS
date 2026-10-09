<?php

namespace App\Domain\Teachers\Repositories;

/** «اللقب العلمي» reference data (teachers.academic_titles — global, seeded, deactivated never deleted). */
interface AcademicTitleRepositoryInterface
{
    /** @return list<array{id: int, code: string, name: string, abbreviation: string|null}> active titles in display order */
    public function active(): array;

    public function activeExists(int $titleId): bool;
}
