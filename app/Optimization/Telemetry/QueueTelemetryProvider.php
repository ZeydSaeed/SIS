<?php

namespace App\Optimization\Telemetry;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

final class QueueTelemetryProvider
{
    /**
     * @return array{depth: ?int, latency_ms: ?float, status: string}
     */
    public function measure(): array
    {
        $connection = config('queue.default');
        if ($connection === 'sync') {
            return ['depth' => null, 'latency_ms' => null, 'status' => 'UNAVAILABLE'];
        }

        try {
            $depth = Queue::size();
        } catch (\Throwable) {
            return ['depth' => null, 'latency_ms' => null, 'status' => 'UNAVAILABLE'];
        }

        $latency = $this->estimateLatencyMs($connection);

        return [
            'depth' => $depth,
            'latency_ms' => $latency,
            'status' => 'MEASURED',
        ];
    }

    private function estimateLatencyMs(string $connection): ?float
    {
        if ($connection !== 'database' || ! Schema::hasTable('jobs')) {
            return null;
        }

        $oldest = DB::table('jobs')->orderBy('created_at')->value('created_at');
        if ($oldest === null) {
            return 0.0;
        }

        return (float) now()->diffInMilliseconds($oldest);
    }
}
