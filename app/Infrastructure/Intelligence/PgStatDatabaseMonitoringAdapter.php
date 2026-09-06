<?php

namespace App\Infrastructure\Intelligence;

use App\Application\Intelligence\Contracts\DatabaseMonitoringPort;
use App\Intelligence\Monitoring\PgStatStatementsCollector;

final class PgStatDatabaseMonitoringAdapter implements DatabaseMonitoringPort
{
    public function __construct(
        private readonly PgStatStatementsCollector $collector,
    ) {}

    public function pgStatExtensionAvailable(): bool
    {
        return $this->collector->extensionAvailable();
    }
}
