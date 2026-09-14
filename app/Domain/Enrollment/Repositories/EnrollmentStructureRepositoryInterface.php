<?php

namespace App\Domain\Enrollment\Repositories;

use App\Domain\Enrollment\Data\ClassSnapshot;
use App\Domain\Enrollment\Data\SectionSnapshot;

interface EnrollmentStructureRepositoryInterface
{
    /**
     * @return list<ClassSnapshot>
     */
    public function listClasses(int $schoolId, ?int $academicYearId = null): array;

    public function findClass(int $schoolId, int $classId): ?ClassSnapshot;

    /**
     * @return list<SectionSnapshot>|null null when class not in school
     */
    public function listSectionsForClass(int $schoolId, int $classId): ?array;

    public function findSection(int $schoolId, int $sectionId): ?SectionSnapshot;

    public function deactivateClass(int $schoolId, int $classId, string $at): void;

    public function reactivateClass(int $schoolId, int $classId, string $at): void;

    public function deactivateSection(int $schoolId, int $sectionId, string $at): void;

    public function reactivateSection(int $schoolId, int $sectionId, string $at): void;
}
