<?php

namespace App\Http\Middleware;

use App\Database\SchemaHelper;
use App\Domain\Academic\Repositories\AcademicYearRepositoryInterface;
use App\Http\Support\AcademicYearContextResolver;
use App\Security\Authorization\SchoolScopeService;
use App\Security\Context\SchoolContext;
use App\Support\Ops\OpsWorkspaceBootstrap;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'locale' => app()->getLocale(),
            'dir' => in_array(substr(app()->getLocale(), 0, 2), ['ar', 'he', 'fa', 'ur'], true) ? 'rtl' : 'ltr',
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'schoolContext' => $this->schoolContextPayload($request),
            'academicYears' => $this->academicYearsPayload(),
            'academicYearId' => $this->currentAcademicYearId($request),
            'opsBootstrap' => $this->opsBootstrapPayload($request),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }

    /**
     * @return array{schoolId: int|null, schools: list<array{id: int, name: string, code: string}>}
     */
    private function schoolContextPayload(Request $request): array
    {
        $user = $request->user();
        if ($user === null) {
            return ['schoolId' => null, 'schools' => []];
        }

        $schoolId = app(SchoolContext::class)->id();
        $allowed = app(SchoolScopeService::class)->allowedSchoolIds($user);
        $schools = $this->loadSchools($allowed);

        return [
            'schoolId' => $schoolId,
            'schools' => $schools,
        ];
    }

    /**
     * @param  list<int>  $ids
     * @return list<array{id: int, name: string, code: string}>
     */
    private function loadSchools(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $table = SchemaHelper::qualified('organization', 'schools');

        try {
            /** @var list<object{id: int|string, name: string, code: string}> $rows */
            $rows = DB::table($table)
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->get(['id', 'name', 'code'])
                ->all();
        } catch (\Throwable) {
            return array_map(
                static fn (int $id): array => ['id' => $id, 'name' => 'مدرسة #'.$id, 'code' => (string) $id],
                $ids,
            );
        }

        $byId = [];
        foreach ($rows as $row) {
            $byId[(int) $row->id] = [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'code' => (string) $row->code,
            ];
        }

        $ordered = [];
        foreach ($ids as $id) {
            $ordered[] = $byId[$id] ?? ['id' => $id, 'name' => 'مدرسة #'.$id, 'code' => (string) $id];
        }

        return $ordered;
    }

    /**
     * @return list<array{id: int, name: string, code: string, is_current: bool}>
     */
    private function academicYearsPayload(): array
    {
        try {
            $years = app(AcademicYearRepositoryInterface::class)->listAll();
        } catch (\Throwable) {
            return [];
        }

        return array_map(
            static fn ($year): array => [
                'id' => $year->id,
                'name' => $year->name,
                'code' => $year->code,
                'is_current' => $year->isCurrent,
            ],
            $years,
        );
    }

    private function currentAcademicYearId(Request $request): ?int
    {
        try {
            return app(AcademicYearContextResolver::class)->resolve(
                $request->filled('academic_year_id') ? (int) $request->query('academic_year_id') : null,
                $request,
            );
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{enabled: bool, needed: bool}
     */
    private function opsBootstrapPayload(Request $request): array
    {
        $bootstrap = app(OpsWorkspaceBootstrap::class);
        $user = $request->user();
        $enabled = $bootstrap->isEnabled();

        return [
            'enabled' => $enabled,
            'needed' => $enabled && $user !== null && $bootstrap->isNeeded($user),
        ];
    }
}
