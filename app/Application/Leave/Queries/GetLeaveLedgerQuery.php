<?php

namespace App\Application\Leave\Queries;

use App\Application\Contracts\Query;
use App\Application\Leave\Support\LeaveViewer;
use App\Domain\Leave\ValueObjects\RequesterKind;

final readonly class GetLeaveLedgerQuery implements Query
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public RequesterKind $kind,
        public int $personId,
        public ?int $typeId,
        public LeaveViewer $viewer,
    ) {}
}
