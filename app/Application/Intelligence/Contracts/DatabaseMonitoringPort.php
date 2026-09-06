<?php

namespace App\Application\Intelligence\Contracts;

interface DatabaseMonitoringPort
{
    public function pgStatExtensionAvailable(): bool;
}
