<?php

namespace App\Observability;

final class WorkloadResolver
{
    public static function forRoute(?string $routeName): string
    {
        if ($routeName === null) {
            return 'unknown';
        }

        $routes = config('sis.observability.workloads.routes', []);

        return (string) ($routes[$routeName] ?? 'general_api');
    }
}
