<?php

namespace App\Application\Teachers\Commands;

use App\Application\Contracts\Command;

/** نوع التعيين (ملاك / مكلف / تنسيب / محاضر / عقد) for one or more teachers in the school/year. */
final readonly class SetTeachersEmploymentTypeCommand implements Command
{
    /** @param  list<int>  $teacherIds */
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public array $teacherIds,
        public int $employmentType,
        public ?string $idempotencyKey,
    ) {}
}
