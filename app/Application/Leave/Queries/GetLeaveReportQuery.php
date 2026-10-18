<?php

namespace App\Application\Leave\Queries;

use App\Application\Contracts\Query;
use App\Application\Leave\Support\LeaveViewer;

final readonly class GetLeaveReportQuery implements Query
{
    /** by_person: approved quantity per person and type · by_type: totals per type and status · pending: open requests and their age · approval_time: hours from submission to decision · holidays: the school calendar. */
    public const KINDS = ['by_person', 'by_type', 'pending', 'approval_time', 'holidays'];

    /**
     * @param  array{from?: string|null, to?: string|null, kind?: int|null, type_ids?: list<int>}  $filters
     */
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public string $kind,
        public array $filters,
        public LeaveViewer $viewer,
    ) {}
}
