<?php

namespace App\Optimization\Telemetry;

use App\Intelligence\Models\QueryMetric;

final class ErrorRateTelemetryProvider
{
    /**
     * @return array{value: ?float, status: string}
     */
    public function measure(int $windowMinutes = 15): array
    {
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable((new QueryMetric)->getTable())) {
                return ['value' => null, 'status' => 'UNAVAILABLE'];
            }
        } catch (\Throwable) {
            return ['value' => null, 'status' => 'UNAVAILABLE'];
        }

        $failed = 0;
        if (\Illuminate\Support\Facades\Schema::hasTable('failed_jobs')) {
            $failed = (int) \Illuminate\Support\Facades\DB::table('failed_jobs')
                ->where('failed_at', '>=', now()->subMinutes($windowMinutes))
                ->count();
        }

        $requestProxy = (int) QueryMetric::query()
            ->where('captured_at', '>=', now()->subMinutes($windowMinutes))
            ->sum('call_count');

        if ($requestProxy <= 0 && $failed === 0) {
            return ['value' => null, 'status' => 'UNKNOWN'];
        }

        if ($requestProxy <= 0) {
            return ['value' => null, 'status' => 'UNKNOWN'];
        }

        return [
            'value' => round(($failed / $requestProxy) * 100, 4),
            'status' => 'MEASURED',
        ];
    }
}
