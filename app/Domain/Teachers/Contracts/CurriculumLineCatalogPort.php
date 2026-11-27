<?php

namespace App\Domain\Teachers\Contracts;

use App\Domain\Teachers\Data\CurriculumLinePlacement;

/** Read-only view of a Curriculum subject line for assigning its teachers. */
interface CurriculumLineCatalogPort
{
    /** The line of a curriculum of the school; `lock` takes a row lock on the line for the rest of the transaction. */
    public function findLine(int $schoolId, int $lineId, bool $lock = false): ?CurriculumLinePlacement;
}
