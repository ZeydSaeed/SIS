<?php

namespace App\Infrastructure\Persistence\Leave;

use App\Domain\Leave\Data\LeavePolicySnapshot;
use App\Domain\Leave\Data\LeavePolicyTerms;
use App\Domain\Leave\Data\LeaveTypeFields;
use App\Domain\Leave\Data\LeaveTypeSnapshot;
use App\Domain\Leave\Repositories\LeaveTypeRepositoryInterface;
use App\Domain\Leave\ValueObjects\DayBasis;
use App\Domain\Leave\ValueObjects\LeaveAudience;
use App\Domain\Leave\ValueObjects\LeaveUnit;
use Illuminate\Support\Facades\DB;

final class EloquentLeaveTypeRepository implements LeaveTypeRepositoryInterface
{
    private const TYPE_COLUMNS = ['id', 'school_id', 'code', 'name_ar', 'name_en', 'description', 'applies_to', 'unit', 'is_sensitive', 'color_hue', 'status'];

    private const POLICY_COLUMNS = ['id', 'leave_type_id', 'version_no', 'day_basis', 'requires_document', 'deducts_balance', 'approval_levels', 'max_per_request', 'annual_entitlement', 'effective_from', 'effective_to'];

    public function find(int $schoolId, int $typeId): ?LeaveTypeSnapshot
    {
        $row = DB::table('leave.types')->where('school_id', $schoolId)->where('id', $typeId)->first(self::TYPE_COLUMNS);
        if ($row === null) {
            return null;
        }
        $policy = DB::table('leave.policies')->where('school_id', $schoolId)->where('leave_type_id', $typeId)->whereNull('effective_to')->first(self::POLICY_COLUMNS);

        return $this->type($row, $policy === null ? null : $this->policySnapshot($policy));
    }

    public function list(int $schoolId): array
    {
        $policies = [];
        foreach (DB::table('leave.policies')->where('school_id', $schoolId)->whereNull('effective_to')->get(self::POLICY_COLUMNS) as $policy) {
            $policies[(int) $policy->leave_type_id] = $this->policySnapshot($policy);
        }

        return DB::table('leave.types')->where('school_id', $schoolId)->orderBy('status')->orderBy('name_ar')->get(self::TYPE_COLUMNS)
            ->map(fn (object $row): LeaveTypeSnapshot => $this->type($row, $policies[(int) $row->id] ?? null))
            ->all();
    }

    public function policy(int $schoolId, int $policyId): ?LeavePolicySnapshot
    {
        $row = DB::table('leave.policies')->where('school_id', $schoolId)->where('id', $policyId)->first(self::POLICY_COLUMNS);

        return $row === null ? null : $this->policySnapshot($row);
    }

    public function policyHistory(int $schoolId, int $typeId): array
    {
        return DB::table('leave.policies')->where('school_id', $schoolId)->where('leave_type_id', $typeId)->orderByDesc('version_no')->get(self::POLICY_COLUMNS)
            ->map(fn (object $row): LeavePolicySnapshot => $this->policySnapshot($row))
            ->all();
    }

    public function codeTaken(int $schoolId, string $code): bool
    {
        return DB::table('leave.types')->where('school_id', $schoolId)->where('code', $code)->exists();
    }

    public function create(int $schoolId, string $code, LeaveTypeFields $fields, ?int $createdBy, string $at): int
    {
        return (int) DB::table('leave.types')->insertGetId($this->fieldsRow($fields) + [
            'school_id' => $schoolId,
            'code' => $code,
            'status' => self::ACTIVE,
            'created_by' => $createdBy,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    public function update(int $typeId, LeaveTypeFields $fields, string $at): void
    {
        DB::table('leave.types')->where('id', $typeId)->update($this->fieldsRow($fields) + ['updated_at' => $at]);
    }

    public function setStatus(int $typeId, int $status, string $at): void
    {
        DB::table('leave.types')->where('id', $typeId)->update(['status' => $status, 'updated_at' => $at]);
    }

    public function publishPolicy(int $schoolId, int $typeId, LeavePolicyTerms $terms, string $effectiveFrom, ?int $createdBy): int
    {
        // Two publishers of the same type are serialised; the partial unique (one current version) backs it up.
        DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ['leave.policies.'.$typeId]);
        $current = DB::table('leave.policies')->where('leave_type_id', $typeId)->whereNull('effective_to')->first(['id', 'version_no', 'effective_from']);
        if ($current !== null) {
            DB::table('leave.policies')->where('id', $current->id)->update(['effective_to' => max($effectiveFrom, (string) $current->effective_from)]);
        }
        $version = (int) (DB::table('leave.policies')->where('leave_type_id', $typeId)->max('version_no') ?? 0) + 1;

        return (int) DB::table('leave.policies')->insertGetId([
            'school_id' => $schoolId,
            'leave_type_id' => $typeId,
            'version_no' => $version,
            'day_basis' => $terms->dayBasis->value,
            'requires_document' => $terms->requiresDocument,
            'deducts_balance' => $terms->deductsBalance,
            'approval_levels' => $terms->approvalLevels,
            'max_per_request' => $terms->maxPerRequest,
            'annual_entitlement' => $terms->annualEntitlement,
            'effective_from' => $effectiveFrom,
            'created_by' => $createdBy,
        ]);
    }

    public function hasRequests(int $schoolId, int $typeId): bool
    {
        return DB::table('leave.requests')->where('school_id', $schoolId)->where('leave_type_id', $typeId)->exists();
    }

    /** @return array<string, mixed> */
    private function fieldsRow(LeaveTypeFields $fields): array
    {
        $text = static fn (?string $v): ?string => $v === null || trim($v) === '' ? null : trim($v);

        return [
            'name_ar' => trim($fields->nameAr),
            'name_en' => $text($fields->nameEn),
            'description' => $text($fields->description),
            'applies_to' => $fields->audience->value,
            'unit' => $fields->unit->value,
            'is_sensitive' => $fields->isSensitive,
            'color_hue' => $fields->colorHue,
        ];
    }

    private function type(object $row, ?LeavePolicySnapshot $policy): LeaveTypeSnapshot
    {
        return new LeaveTypeSnapshot(
            id: (int) $row->id,
            schoolId: (int) $row->school_id,
            code: (string) $row->code,
            nameAr: (string) $row->name_ar,
            nameEn: $row->name_en !== null ? (string) $row->name_en : null,
            description: $row->description !== null ? (string) $row->description : null,
            audience: LeaveAudience::from((int) $row->applies_to),
            unit: LeaveUnit::from((int) $row->unit),
            isSensitive: (bool) $row->is_sensitive,
            colorHue: $row->color_hue !== null ? (int) $row->color_hue : null,
            status: (int) $row->status,
            policy: $policy,
        );
    }

    private function policySnapshot(object $row): LeavePolicySnapshot
    {
        return new LeavePolicySnapshot(
            id: (int) $row->id,
            leaveTypeId: (int) $row->leave_type_id,
            versionNo: (int) $row->version_no,
            dayBasis: DayBasis::from((int) $row->day_basis),
            requiresDocument: (bool) $row->requires_document,
            deductsBalance: (bool) $row->deducts_balance,
            approvalLevels: (int) $row->approval_levels,
            maxPerRequest: $row->max_per_request !== null ? (float) $row->max_per_request : null,
            annualEntitlement: $row->annual_entitlement !== null ? (float) $row->annual_entitlement : null,
            effectiveFrom: substr((string) $row->effective_from, 0, 10),
            effectiveTo: $row->effective_to !== null ? substr((string) $row->effective_to, 0, 10) : null,
        );
    }
}
