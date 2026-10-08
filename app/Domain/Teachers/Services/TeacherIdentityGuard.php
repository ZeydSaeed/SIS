<?php

namespace App\Domain\Teachers\Services;

use App\Domain\Teachers\Repositories\TeacherRepositoryInterface;

/**
 * Identity preconditions of registering / editing a teacher that the database enforces with a unique key or a foreign
 * key — answered with a domain code instead of a constraint violation (HTTP 500).
 */
final class TeacherIdentityGuard
{
    public function __construct(
        private readonly TeacherRepositoryInterface $teachers,
    ) {}

    /** @return string|null error code of the first rule broken */
    public function rejectionCode(?int $academicYearId, ?string $nationalId, ?int $exceptTeacherId = null): ?string
    {
        if ($academicYearId !== null && ! $this->teachers->academicYearExists($academicYearId)) {
            return 'teachers.academic_year_invalid';
        }
        if ($nationalId !== null && trim($nationalId) !== '' && $this->teachers->nationalIdTaken(trim($nationalId), $exceptTeacherId)) {
            return 'teachers.national_id_taken';
        }

        return null;
    }
}
