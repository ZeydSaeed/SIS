<?php

namespace App\Application\Attendance\Queries;

use App\Application\Attendance\Support\SheetActor;
use App\Application\Contracts\Query;

final readonly class GetAttendanceSheetQuery implements Query
{
    public function __construct(
        public int $schoolId,
        public int $sessionId,
        public SheetActor $actor,
        /** The user may read any sheet of the school (reviewer / report viewer), not only their own lessons. */
        public bool $canViewAny,
        public bool $canCorrect,
    ) {}
}
