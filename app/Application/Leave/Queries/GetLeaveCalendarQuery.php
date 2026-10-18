<?php

namespace App\Application\Leave\Queries;

use App\Application\Contracts\Query;
use App\Application\Leave\Support\LeaveViewer;

final readonly class GetLeaveCalendarQuery implements Query
{
    /** The widest window one read may cover (a month view with its leading / trailing weeks). */
    public const MAX_DAYS = 62;

    /**
     * @param  array{kind?: int|null, type_ids?: list<int>, statuses?: list<int>, layers?: list<string>}  $filters
     */
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public string $from,
        public string $to,
        public LeaveViewer $viewer,
        public array $filters,
    ) {}
}
