<?php

namespace App\Http\Requests\Admission;

use App\Database\SchemaHelper;
use App\Security\Authorization\SchoolScopeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * The school chosen on an application must be one of the user's linked, active schools —
 * the application (and its student) is filed in that school's tenant.
 */
trait ValidatesApplicationSchool
{
    /** @return list<mixed> */
    protected function applicationSchoolRule(): array
    {
        $user = $this->user();
        $allowed = $user === null ? [] : app(SchoolScopeService::class)->allowedSchoolIds($user);
        $active = $allowed === []
            ? []
            : DB::table(SchemaHelper::qualified('organization', 'schools'))
                ->whereIn('id', $allowed)
                ->where('status', 1)
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->all();

        return ['required', 'integer', Rule::in($active)];
    }

    /** @return array<string, string> */
    protected function applicationSchoolMessages(): array
    {
        return [
            'target_school_id.required' => 'المدرسة مطلوبة.',
            'target_school_id.in' => 'المدرسة المختارة غير مرتبطة بحسابك أو غير نشطة.',
        ];
    }
}
