<?php

namespace App\Optimization\SelfHealing;

use App\Intelligence\Models\MonitoringSnapshot;

final class DataScaleContext
{
    /**
     * @return array<string, mixed>
     */
    public function capture(): array
    {
        $health = MonitoringSnapshot::query()->orderByDesc('captured_at')->first();

        return [
            'database_size_mb' => $health?->database_size_mb,
            'connection_count' => $health?->connection_count,
            'scale_tier' => $this->tier($health?->database_size_mb),
        ];
    }

    public function tier(?float $databaseSizeMb): string
    {
        if ($databaseSizeMb === null) {
            return 'unknown';
        }

        $small = (float) config('optimization.data_scale.small_max_mb', 1024);
        $medium = (float) config('optimization.data_scale.medium_max_mb', 10240);

        if ($databaseSizeMb <= $small) {
            return 'small';
        }
        if ($databaseSizeMb <= $medium) {
            return 'medium';
        }

        return 'large';
    }

    /**
     * @param  array<string, mixed>  $scaleContext
     */
    public function allowsAutonomousOptimization(array $scaleContext): bool
    {
        if (($scaleContext['scale_tier'] ?? 'unknown') === 'unknown') {
            return ! config('optimization.data_scale.block_autonomous_on_unknown', true);
        }

        return true;
    }
}
