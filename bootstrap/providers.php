<?php

use App\Providers\AppServiceProvider;
use App\Providers\ArchitectureServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\IntelligenceServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    ArchitectureServiceProvider::class,
    IntelligenceServiceProvider::class,
];
