<?php

namespace App\Infrastructure\Persistence\Admission;

use App\Database\SchemaHelper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Redis/cache layer for admission workspace reads.
 * PostgreSQL remains source of truth — short TTLs + write-path forget.
 */
final class AdmissionWorkspaceCache
{
    private const TTL_REFS_SECONDS = 3600;

    private const TTL_SHELL_SECONDS = 20;

    private const TTL_STATUS_SECONDS = 15;

    /**
     * @template T
     * @param  callable(): T  $resolver
     * @return T
     */
    public function rememberGradeLevels(callable $resolver): mixed
    {
        return Cache::remember('admission:grade_levels', self::TTL_REFS_SECONDS, $resolver);
    }

    /**
     * @template T
     * @param  callable(): T  $resolver
     * @return T
     */
    public function rememberSchoolRefs(int $schoolId, callable $resolver): mixed
    {
        return Cache::remember(
            "admission:refs:{$schoolId}",
            self::TTL_REFS_SECONDS,
            $resolver,
        );
    }

    /**
     * @template T
     * @param  callable(): T  $resolver
     * @return T
     */
    public function rememberPeriods(int $schoolId, int $academicYearId, callable $resolver): mixed
    {
        return Cache::remember(
            $this->periodsKey($schoolId, $academicYearId),
            self::TTL_SHELL_SECONDS,
            $resolver,
        );
    }

    /**
     * @template T
     * @param  callable(): T  $resolver
     * @return T
     */
    public function rememberPeriodCounts(int $schoolId, int $academicYearId, callable $resolver): mixed
    {
        return Cache::remember(
            $this->periodCountsKey($schoolId, $academicYearId),
            self::TTL_SHELL_SECONDS,
            $resolver,
        );
    }

    /**
     * @template T
     * @param  callable(): T  $resolver
     * @return T
     */
    public function rememberStatusCounts(
        int $schoolId,
        int $academicYearId,
        ?int $applicationPeriodId,
        callable $resolver,
    ): mixed {
        return Cache::remember(
            $this->statusCountsKey($schoolId, $academicYearId, $applicationPeriodId),
            self::TTL_STATUS_SECONDS,
            $resolver,
        );
    }

    public function forgetSchoolYear(int $schoolId, int $academicYearId): void
    {
        Cache::forget($this->periodsKey($schoolId, $academicYearId));
        Cache::forget($this->periodCountsKey($schoolId, $academicYearId));
        Cache::forget($this->statusCountsKey($schoolId, $academicYearId, null));
    }

    public function forgetStatusForPeriod(int $schoolId, int $academicYearId, int $periodId): void
    {
        Cache::forget($this->statusCountsKey($schoolId, $academicYearId, $periodId));
        Cache::forget($this->statusCountsKey($schoolId, $academicYearId, null));
        Cache::forget($this->periodCountsKey($schoolId, $academicYearId));
    }

    public function forgetSchoolRefs(int $schoolId): void
    {
        Cache::forget("admission:refs:{$schoolId}");
    }

    public function forgetForPeriodId(int $periodId): void
    {
        $row = DB::table(SchemaHelper::qualified('admission', 'application_periods'))
            ->where('id', $periodId)
            ->first(['school_id', 'academic_year_id']);

        if ($row === null) {
            return;
        }

        $schoolId = (int) $row->school_id;
        $yearId = (int) $row->academic_year_id;
        $this->forgetSchoolYear($schoolId, $yearId);
        $this->forgetStatusForPeriod($schoolId, $yearId, $periodId);
    }

    public function forgetForApplicationId(int $applicationId): void
    {
        $row = DB::table(SchemaHelper::qualified('admission', 'applications').' as apps')
            ->join(
                SchemaHelper::qualified('admission', 'application_periods').' as periods',
                'periods.id',
                '=',
                'apps.application_period_id',
            )
            ->where('apps.id', $applicationId)
            ->first([
                'periods.school_id',
                'periods.academic_year_id',
                'apps.application_period_id',
            ]);

        if ($row === null) {
            return;
        }

        $schoolId = (int) $row->school_id;
        $yearId = (int) $row->academic_year_id;
        $periodId = (int) $row->application_period_id;
        $this->forgetSchoolYear($schoolId, $yearId);
        $this->forgetStatusForPeriod($schoolId, $yearId, $periodId);
    }

    private function periodsKey(int $schoolId, int $academicYearId): string
    {
        return "admission:periods:{$schoolId}:{$academicYearId}";
    }

    private function periodCountsKey(int $schoolId, int $academicYearId): string
    {
        return "admission:period_counts:{$schoolId}:{$academicYearId}";
    }

    private function statusCountsKey(int $schoolId, int $academicYearId, ?int $periodId): string
    {
        $periodKey = $periodId === null ? 'all' : (string) $periodId;

        return "admission:status_counts:{$schoolId}:{$academicYearId}:{$periodKey}";
    }
}
