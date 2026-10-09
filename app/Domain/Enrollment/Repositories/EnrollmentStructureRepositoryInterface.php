<?php

namespace App\Domain\Enrollment\Repositories;

use App\Domain\Enrollment\Data\ClassSnapshot;
use App\Domain\Enrollment\Data\SectionSnapshot;
use App\Domain\Shared\ValueObjects\DisplayAppearance;

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

    public function gradeLevelExists(int $gradeLevelId): bool;

    public function classNameTaken(int $schoolId, int $academicYearId, string $name, ?int $exceptClassId = null): bool;

    public function sectionNameTaken(int $classId, string $name, ?int $exceptSectionId = null): bool;

    /** Generates the next CLS-n code for the school/year (inside the transaction). */
    public function createClass(int $schoolId, int $academicYearId, int $gradeLevelId, string $name, ?int $capacity, string $at): int;

    public function updateClass(int $schoolId, int $classId, int $gradeLevelId, string $name, ?int $capacity, string $at): void;

    /** Generates the next SEC-n code for the class (inside the transaction). */
    public function createSection(int $schoolId, int $classId, string $name, ?int $capacity, ?int $homeroomTeacherId, string $at): int;

    public function updateSection(int $schoolId, int $sectionId, string $name, ?int $capacity, ?int $homeroomTeacherId, string $at): void;

    /**
     * «الاختصار واللون» of the year's classes and sections, by id.
     *
     * @return array{classes: array<int, array{abbreviation: string|null, color_hue: int|null}>, sections: array<int, array{abbreviation: string|null, color_hue: int|null}>}
     */
    public function appearanceForYear(int $schoolId, int $academicYearId): array;

    /** «الاختصار واللون» of a class ('class') or section ('section') of the school. */
    public function setAppearance(string $target, int $schoolId, int $id, DisplayAppearance $appearance, string $at): void;

    /** Sum of the capacities of the class's active sections (a section without a capacity counts 0). */
    public function activeSectionCapacitySum(int $schoolId, int $classId): int;

    public function countActiveEnrollmentsInClass(int $schoolId, int $classId): int;

    public function countActiveEnrollmentsInSection(int $schoolId, int $sectionId): int;

    /**
     * Active enrollments per section of the school's classes in the year.
     *
     * @return array<int, int> section id → active enrollments
     */
    public function activeEnrollmentCountsBySection(int $schoolId, int $academicYearId): array;

    /**
     * Sections of every class of the school in the year (one query — structure page).
     *
     * @return list<SectionSnapshot>
     */
    public function listSectionsForYear(int $schoolId, int $academicYearId): array;
}
