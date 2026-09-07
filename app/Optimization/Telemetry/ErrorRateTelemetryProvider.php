<?php

namespace App\Optimization\Telemetry;

use App\Intelligence\Models\QueryMetric;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ErrorRateTelemetryProvider
{
    /**
     * @return array{value: ?float, status: string}
     */
    public function measure(int $windowMinutes = 15): array
    {
        try {
            if (! Schema::hasTable((new QueryMetric)->getTable())) {
                return ['value' => null, 'status' => 'UNAVAILABLE'];
            }
        } catch (\Throwable) {
            return ['value' => null, 'status' => 'UNAVAILABLE'];
        }

        $failed = 0;
        if (Schema::hasTable('failed_jobs')) {
            $failed = (int) DB::table('failed_jobs')
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
