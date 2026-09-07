<?php

use App\Providers\AppServiceProvider;
use App\Providers\ArchitectureServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\IntelligenceServiceProvider;
use App\Providers\OptimizationServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    ArchitectureServiceProvider::class,
    IntelligenceServiceProvider::class,
    OptimizationServiceProvider::class,
];
