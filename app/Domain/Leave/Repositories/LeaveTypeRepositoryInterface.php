<?php

namespace App\Domain\Leave\Repositories;

use App\Domain\Leave\Data\LeavePolicySnapshot;
use App\Domain\Leave\Data\LeavePolicyTerms;
use App\Domain\Leave\Data\LeaveTypeFields;
use App\Domain\Leave\Data\LeaveTypeSnapshot;

/** leave.types + leave.policies of one school (the school is always passed; RLS enforces it again). */
interface LeaveTypeRepositoryInterface
{
    public const ACTIVE = 1;

    public const INACTIVE = 2;

    /** The type with its current policy, or null when it is not this school's. */
    public function find(int $schoolId, int $typeId): ?LeaveTypeSnapshot;

    /** @return list<LeaveTypeSnapshot> ordered by name */
    public function list(int $schoolId): array;

    public function policy(int $schoolId, int $policyId): ?LeavePolicySnapshot;

    /** @return list<LeavePolicySnapshot> newest first */
    public function policyHistory(int $schoolId, int $typeId): array;

    public function codeTaken(int $schoolId, string $code): bool;

    public function create(int $schoolId, string $code, LeaveTypeFields $fields, ?int $createdBy, string $at): int;

    public function update(int $typeId, LeaveTypeFields $fields, string $at): void;

    public function setStatus(int $typeId, int $status, string $at): void;

    /** Closes the current version (effective_to = $effectiveFrom) and inserts the next one; returns its id. */
    public function publishPolicy(int $schoolId, int $typeId, LeavePolicyTerms $terms, string $effectiveFrom, ?int $createdBy): int;

    /** Whether any request was ever filed under the type (its unit is then fixed). */
    public function hasRequests(int $schoolId, int $typeId): bool;
}
