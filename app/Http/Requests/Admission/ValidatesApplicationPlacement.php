<?php

namespace App\Http\Requests\Admission;

use App\Database\SchemaHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;

/**
 * The branch (الفرع) must belong to the school chosen on the application, and the
 * department (الاختصاص) to that branch — the lists come from the school's catalog.
 */
trait ValidatesApplicationPlacement
{
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->hasAny(['target_school_id', 'branch_name', 'department_name'])) {
                return;
            }

            $schoolId = (int) $this->input('target_school_id');
            $branchName = trim((string) $this->input('branch_name'));
            $departmentName = trim((string) $this->input('department_name'));

            $branches = DB::table(SchemaHelper::qualified('organization', 'branches'))
                ->where('school_id', $schoolId)
                ->where('status', 1)
                ->get(['id', 'name']);
            if ($branches->isEmpty()) {
                $validator->errors()->add('branch_name', 'لا توجد فروع معرّفة لهذه المدرسة.');

                return;
            }

            $branch = $branches->first(fn ($row): bool => trim((string) $row->name) === $branchName);
            if ($branch === null) {
                $validator->errors()->add('branch_name', 'الفرع المختار لا يتبع المدرسة المختارة.');

                return;
            }
            if ($this->filled('branch_id') && (int) $this->input('branch_id') !== (int) $branch->id) {
                $validator->errors()->add('branch_id', 'الفرع المختار لا يتبع المدرسة المختارة.');
            }

            $departmentExists = DB::table(SchemaHelper::qualified('organization', 'departments'))
                ->where('school_id', $schoolId)
                ->where('branch_id', $branch->id)
                ->where('status', 1)
                ->whereRaw('trim(name) = ?', [$departmentName])
                ->exists();
            if (! $departmentExists) {
                $validator->errors()->add('department_name', 'الاختصاص المختار لا يتبع الفرع المختار.');
            }
        });
    }
}
