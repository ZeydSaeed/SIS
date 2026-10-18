<?php

namespace App\Application\Leave\Contracts;

use App\Domain\Leave\Data\LeaveRequester;
use App\Domain\Leave\ValueObjects\LeaveAudience;
use App\Domain\Leave\ValueObjects\RequesterKind;

/**
 * The school's staff as the leave module sees them — read from teachers.teacher_schools (active membership) and
 * hr.employee_schools. An employee linked to a teacher (hr.employees.teacher_id) is the teacher: listed once.
 */
interface LeaveStaffDirectoryInterface
{
    /** The person when they work in the school that year, else null. */
    public function requester(int $schoolId, int $academicYearId, RequesterKind $kind, int $personId): ?LeaveRequester;

    /** The person of an existing request (even after they left the school) — for the self-approval rule. */
    public function person(RequesterKind $kind, int $personId): ?LeaveRequester;

    /** @return list<LeaveRequester> the staff identities linked to the user account in the school that year */
    public function ownRequesters(int $userId, int $schoolId, int $academicYearId): array;

    /** @return list<LeaveRequester> everyone the type applies to, ordered by name */
    public function staff(int $schoolId, int $academicYearId, LeaveAudience $audience = LeaveAudience::All): array;
}
