<?php

namespace App\Application\Curriculum\Commands;

use App\Application\Contracts\Command;

final readonly class CreateCurriculumCommand implements Command
{
    /**
     * @param  list<int>  $subjectIds  When non-empty, link these subjects instead of specialization catalog templates.
     */
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $gradeLevelId,
        public string $name,
        public ?int $specializationId,
        public ?string $idempotencyKey,
        public array $subjectIds = [],
        /** الاختصاص (branch department) the curriculum belongs to. */
        public ?int $departmentId = null,
        /** الفرع / الصف (enrollment.classes) / الشعبة — required by the «المناهج» page. */
        public ?int $branchId = null,
        public ?int $classId = null,
        public ?int $sectionId = null,
    ) {}
}
