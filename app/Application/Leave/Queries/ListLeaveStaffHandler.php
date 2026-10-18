<?php

namespace App\Application\Leave\Queries;

use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Application\Leave\Contracts\LeaveStaffDirectoryInterface;
use App\Domain\Leave\Data\LeaveRequester;

/**
 * Who a request may be filed for: every staff member of the school-year for a holder of «إدارة طلبات الإجازة»,
 * otherwise only the user's own staff identity (teacher and / or employee linked to the account).
 */
final class ListLeaveStaffHandler implements QueryHandler
{
    public function __construct(
        private readonly LeaveStaffDirectoryInterface $staff,
    ) {}

    /** @return list<array{kind: int, id: int, name: string, own: bool}> */
    public function handle(Query $query): array
    {
        assert($query instanceof ListLeaveStaffQuery);
        $own = $this->staff->ownRequesters($query->viewer->userId, $query->schoolId, $query->academicYearId);
        $people = $query->canManage || $query->viewer->seesAll ? $this->staff->staff($query->schoolId, $query->academicYearId) : $own;
        $ownKeys = array_map(static fn (LeaveRequester $r): string => $r->kind->value.':'.$r->personId, $own);

        return array_map(static fn (LeaveRequester $r): array => [
            'kind' => $r->kind->value,
            'id' => $r->personId,
            'name' => $r->name,
            'own' => in_array($r->kind->value.':'.$r->personId, $ownKeys, true),
        ], $people);
    }
}
