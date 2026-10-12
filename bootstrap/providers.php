<?php

use App\Providers\AppServiceProvider;
use App\Providers\ArchitectureServiceProvider;
use App\Providers\AttendanceServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\IntelligenceServiceProvider;
use App\Providers\ObservabilityServiceProvider;
use App\Providers\OptimizationServiceProvider;
use App\Providers\SecurityServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    SecurityServiceProvider::class,
    ArchitectureServiceProvider::class,
    AttendanceServiceProvider::class,
    ObservabilityServiceProvider::class,
    IntelligenceServiceProvider::class,
    OptimizationServiceProvider::class,
];
