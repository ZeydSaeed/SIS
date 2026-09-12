<?php

namespace App\Observability;

final class WorkloadResolver
{
    public static function forRoute(?string $routeName): string
    {
        if ($routeName === null || $routeName === '') {
            return 'unknown';
        }

        $routes = config('sis.observability.workloads.routes', []);
        if (isset($routes[$routeName])) {
            return (string) $routes[$routeName];
        }

        $prefixes = config('sis.observability.workloads.prefixes', []);
        if ($prefixes !== []) {
            uksort($prefixes, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
            foreach ($prefixes as $prefix => $workload) {
                if (str_starts_with($routeName, (string) $prefix)) {
                    return (string) $workload;
                }
            }
        }

        return 'general_api';
    }
}
