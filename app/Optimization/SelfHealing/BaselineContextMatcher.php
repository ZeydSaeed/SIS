<?php

namespace App\Optimization\SelfHealing;

final class BaselineContextMatcher
{
    /**
     * @param  array<string, mixed>  $baseline
     * @param  array<string, mixed>  $currentFingerprint
     */
    public function isCompatible(array $baseline, array $currentFingerprint): bool
    {
        $stored = $baseline['context_fingerprint'] ?? [];
        if ($stored === []) {
            return false;
        }

        $keys = config('optimization.baseline.context_match_keys', [
            'environment',
            'cpu_cores',
            'memory_limit_mb',
        ]);

        foreach ($keys as $key) {
            $a = $stored[$key] ?? null;
            $b = $currentFingerprint[$key] ?? null;
            if ($a === null || $b === null) {
                continue;
            }
            if (is_numeric($a) && is_numeric($b)) {
                if ((float) $a !== (float) $b) {
                    return false;
                }
            } elseif ($a !== $b) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $telemetry
     * @return array<string, mixed>
     */
    public function fingerprintFromTelemetry(array $telemetry): array
    {
        return (array) ($telemetry['context_fingerprint'] ?? []);
    }
}
