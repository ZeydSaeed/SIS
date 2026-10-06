<?php

namespace App\Http\Requests\Admission;

use App\Database\SchemaHelper;
use App\Security\Authorization\SchoolScopeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * A period's directorate (المديرية) must be an active directorate of one of the
 * user's linked, active schools.
 */
trait ValidatesApplicationDirectorate
{
    /** @return list<mixed> */
    protected function applicationDirectorateRule(): array
    {
        return ['required', 'integer', Rule::in($this->userDirectorateIds())];
    }

    /**
     * Active directorates of the user's linked, active schools (periods the user may manage).
     *
     * @return list<int>
     */
    public function userDirectorateIds(): array
    {
        $user = $this->user();
        $schoolIds = $user === null ? [] : app(SchoolScopeService::class)->allowedSchoolIds($user);

        return $schoolIds === []
            ? []
            : DB::table(SchemaHelper::qualified('organization', 'schools').' as s')
                ->join(SchemaHelper::qualified('organization', 'directorates').' as d', 'd.id', '=', 's.directorate_id')
                ->whereIn('s.id', $schoolIds)
                ->where('s.status', 1)
                ->where('d.status', 1)
                ->distinct()
                ->pluck('d.id')
                ->map(static fn ($id): int => (int) $id)
                ->values()
                ->all();
    }

    /** @return array<string, string> */
    protected function applicationDirectorateMessages(): array
    {
        return [
            'directorate_id.required' => 'المديرية مطلوبة.',
            'directorate_id.in' => 'المديرية المختارة غير مرتبطة بمدارس حسابك أو غير نشطة.',
        ];
    }
}
