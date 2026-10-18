<?php

namespace App\Infrastructure\Persistence\Leave;

use App\Application\Leave\Contracts\LeaveStaffDirectoryInterface;
use App\Domain\Leave\Data\LeaveRequester;
use App\Domain\Leave\ValueObjects\LeaveAudience;
use App\Domain\Leave\ValueObjects\RequesterKind;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Staff of a school-year from their owning tables (never copied): teachers with an active membership
 * (teachers.teacher_schools.left_at IS NULL) and active employees of hr.employee_schools. An employee linked to a
 * teacher record is that teacher — left out of the employees.
 */
final class EloquentLeaveStaffDirectory implements LeaveStaffDirectoryInterface
{
    private const ACTIVE_EMPLOYEE = 1;

    public function requester(int $schoolId, int $academicYearId, RequesterKind $kind, int $personId): ?LeaveRequester
    {
        $query = $kind === RequesterKind::Teacher ? $this->teachers($schoolId, $academicYearId) : $this->employees($schoolId, $academicYearId);
        $row = $query->where($kind === RequesterKind::Teacher ? 't.id' : 'e.id', $personId)->first();

        return $row === null ? null : $this->requesterOf($kind, $row);
    }

    public function person(RequesterKind $kind, int $personId): ?LeaveRequester
    {
        $row = $kind === RequesterKind::Teacher
            ? DB::table('teachers.teachers as t')->where('t.id', $personId)->first(['t.id', 't.full_name', 't.user_id'])
            : DB::table('hr.employees as e')->where('e.id', $personId)->first(['e.id', 'e.full_name', 'e.user_id']);

        return $row === null ? null : $this->requesterOf($kind, $row);
    }

    public function ownRequesters(int $userId, int $schoolId, int $academicYearId): array
    {
        $teachers = $this->teachers($schoolId, $academicYearId)->where('t.user_id', $userId)->get()
            ->map(fn (object $row): LeaveRequester => $this->requesterOf(RequesterKind::Teacher, $row))->all();
        $employees = $this->employees($schoolId, $academicYearId)->where('e.user_id', $userId)->get()
            ->map(fn (object $row): LeaveRequester => $this->requesterOf(RequesterKind::Employee, $row))->all();

        return [...$teachers, ...$employees];
    }

    public function staff(int $schoolId, int $academicYearId, LeaveAudience $audience = LeaveAudience::All): array
    {
        $out = [];
        if ($audience->allows(RequesterKind::Teacher)) {
            foreach ($this->teachers($schoolId, $academicYearId)->orderBy('t.full_name')->get() as $row) {
                $out[] = $this->requesterOf(RequesterKind::Teacher, $row);
            }
        }
        if ($audience->allows(RequesterKind::Employee)) {
            foreach ($this->employees($schoolId, $academicYearId)->orderBy('e.full_name')->get() as $row) {
                $out[] = $this->requesterOf(RequesterKind::Employee, $row);
            }
        }

        return $out;
    }

    private function teachers(int $schoolId, int $academicYearId): Builder
    {
        return DB::table('teachers.teachers as t')
            ->whereExists(static fn (Builder $q) => $q->selectRaw('1')->from('teachers.teacher_schools as ts')
                ->whereColumn('ts.teacher_id', 't.id')
                ->where('ts.school_id', $schoolId)
                ->where('ts.academic_year_id', $academicYearId)
                ->whereNull('ts.left_at'))
            ->select(['t.id', 't.full_name', 't.user_id']);
    }

    private function employees(int $schoolId, int $academicYearId): Builder
    {
        return DB::table('hr.employees as e')
            ->whereNull('e.teacher_id')
            ->where('e.status', self::ACTIVE_EMPLOYEE)
            ->whereExists(static fn (Builder $q) => $q->selectRaw('1')->from('hr.employee_schools as es')
                ->whereColumn('es.employee_id', 'e.id')
                ->where('es.school_id', $schoolId)
                ->where('es.academic_year_id', $academicYearId))
            ->select(['e.id', 'e.full_name', 'e.user_id']);
    }

    private function requesterOf(RequesterKind $kind, object $row): LeaveRequester
    {
        return new LeaveRequester($kind, (int) $row->id, $row->user_id !== null ? (int) $row->user_id : null, (string) $row->full_name);
    }
}
