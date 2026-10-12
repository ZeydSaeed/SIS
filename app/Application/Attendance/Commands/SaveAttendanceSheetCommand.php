<?php

namespace App\Application\Attendance\Commands;

use App\Application\Attendance\Support\SheetActor;
use App\Application\Contracts\Command;
use App\Domain\Attendance\Data\SheetMark;

/** Save the sheet as a draft (resume later). Marks may include «غير محدد» (0). */
final readonly class SaveAttendanceSheetCommand implements Command
{
    /**
     * @param  list<SheetMark>  $marks
     */
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $sessionId,
        public int $expectedVersion,
        public array $marks,
        public SheetActor $actor,
        public string $idempotencyKey,
    ) {}
}
