<?php

namespace App\Application\Organization\Queries;

use App\Application\Contracts\Query;

/** «الغرف الدراسية»: one server page of the school's rooms (search, filters, sort) + types, branches and totals. */
final readonly class GetRoomCatalogueQuery implements Query
{
    public const PER_PAGE = 25;

    /** @param  array{search?: string|null, branch_ids?: list<int>, room_type_ids?: list<int>, status?: int|null, practical?: bool|null}  $filters */
    public function __construct(
        public int $schoolId,
        public array $filters = [],
        public string $sort = 'code',
        public string $direction = 'asc',
        public int $page = 1,
        public int $perPage = self::PER_PAGE,
    ) {}
}
