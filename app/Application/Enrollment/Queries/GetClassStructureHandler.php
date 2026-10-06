<?php

namespace App\Application\Enrollment\Queries;

use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Application\Enrollment\DTOs\ClassDTO;
use App\Application\Enrollment\DTOs\ClassStructureDTO;
use App\Application\Enrollment\DTOs\SectionDTO;
use App\Domain\Enrollment\Data\ClassSnapshot;
use App\Domain\Enrollment\Data\SectionSnapshot;
use App\Domain\Enrollment\Repositories\EnrollmentStructureRepositoryInterface;

/** Three queries (classes, sections, enrollment counts) — no N+1 per class. */
final class GetClassStructureHandler implements QueryHandler
{
    public function __construct(
        private readonly EnrollmentStructureRepositoryInterface $structure,
    ) {}

    public function handle(Query $query): ClassStructureDTO
    {
        assert($query instanceof GetClassStructureQuery);

        $sectionsByClass = [];
        foreach ($this->structure->listSectionsForYear($query->schoolId, $query->academicYearId) as $section) {
            $sectionsByClass[$section->classId][] = $this->mapSection($section);
        }

        return new ClassStructureDTO(
            classes: array_map(
                fn (ClassSnapshot $class): ClassDTO => $this->mapClass($class),
                $this->structure->listClasses($query->schoolId, $query->academicYearId),
            ),
            sectionsByClass: $sectionsByClass,
            enrolledBySection: $this->structure->activeEnrollmentCountsBySection($query->schoolId, $query->academicYearId),
        );
    }

    private function mapClass(ClassSnapshot $c): ClassDTO
    {
        return new ClassDTO(
            $c->id,
            $c->schoolId,
            $c->academicYearId,
            $c->gradeLevelId,
            $c->code,
            $c->name,
            $c->capacity,
            $c->status,
            $c->createdAt,
            $c->updatedAt,
        );
    }

    private function mapSection(SectionSnapshot $s): SectionDTO
    {
        return new SectionDTO(
            $s->id,
            $s->classId,
            $s->schoolId,
            $s->code,
            $s->name,
            $s->capacity,
            $s->homeroomTeacherId,
            $s->status,
            $s->createdAt,
            $s->updatedAt,
        );
    }
}
