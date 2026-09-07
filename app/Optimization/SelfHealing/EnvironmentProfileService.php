<?php

namespace App\Optimization\SelfHealing;

use Illuminate\Support\Facades\File;

final class EnvironmentProfileService
{
    /**
     * @return array<string, mixed>
     */
    public function capture(): array
    {
        $profile = [
            'captured_at' => now()->toIso8601String(),
            'environment' => config('app.env'),
            'app_version' => config('app.version', 'dev'),
            'php_version' => PHP_VERSION,
            'os' => PHP_OS,
            'cpu_cores' => $this->cpuCores(),
            'memory_limit_mb' => $this->memoryLimitMb(),
            'database_driver' => config('database.default'),
        ];

        File::ensureDirectoryExists(dirname(config('optimization.environment_profile_path')));
        File::put(config('optimization.environment_profile_path'), json_encode($profile, JSON_PRETTY_PRINT));

        return $profile;
    }

    /**
     * @return array<string, mixed>
     */
    public function current(): array
    {
        $path = config('optimization.environment_profile_path');
        if (! File::exists($path)) {
            return $this->capture();
        }

        $data = json_decode(File::get($path), true);

        return is_array($data) ? $data : $this->capture();
    }

    /**
     * @param  array<string, mixed>  $previous
     * @param  array<string, mixed>  $current
     */
    public function hasEnvironmentChanged(array $previous, array $current): bool
    {
        $keys = ['cpu_cores', 'memory_limit_mb', 'php_version', 'environment'];

        foreach ($keys as $key) {
            if (($previous[$key] ?? null) !== ($current[$key] ?? null)) {
                return true;
            }
        }

        return false;
    }

    private function cpuCores(): int
    {
        $cores = getenv('NUMBER_OF_PROCESSORS');

        return $cores !== false ? max(1, (int) $cores) : 1;
    }

    private function memoryLimitMb(): ?float
    {
        $limit = ini_get('memory_limit');
        if ($limit === false || $limit === '-1') {
            return null;
        }

        $value = (float) $limit;
        if (str_ends_with(strtolower($limit), 'g')) {
            return $value * 1024;
        }
        if (str_ends_with(strtolower($limit), 'k')) {
            return $value / 1024;
        }

        return $value;
    }
}
