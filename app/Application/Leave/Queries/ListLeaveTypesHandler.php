<?php

namespace App\Application\Leave\Queries;

use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Domain\Leave\Data\LeavePolicySnapshot;
use App\Domain\Leave\Data\LeaveTypeSnapshot;
use App\Domain\Leave\Repositories\LeaveTypeRepositoryInterface;

/** «أنواع الإجازات والسياسات»: the school's types, each with its current policy (and, on demand, every version). */
final class ListLeaveTypesHandler implements QueryHandler
{
    public function __construct(
        private readonly LeaveTypeRepositoryInterface $types,
    ) {}

    /** @return list<array<string, mixed>> */
    public function handle(Query $query): array
    {
        assert($query instanceof ListLeaveTypesQuery);

        return array_map(fn (LeaveTypeSnapshot $t): array => [
            'id' => $t->id,
            'code' => $t->code,
            'name_ar' => $t->nameAr,
            'name_en' => $t->nameEn,
            'description' => $t->description,
            'applies_to' => $t->audience->value,
            'unit' => $t->unit->value,
            'is_sensitive' => $t->isSensitive,
            'color_hue' => $t->colorHue,
            'status' => $t->status,
            'policy' => $t->policy?->toArray(),
            'history' => $query->withHistory
                ? array_map(static fn (LeavePolicySnapshot $p): array => $p->toArray(), $this->types->policyHistory($query->schoolId, $t->id))
                : [],
        ], $this->types->list($query->schoolId));
    }
}
