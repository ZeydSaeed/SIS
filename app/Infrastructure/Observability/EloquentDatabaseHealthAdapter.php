<?php

namespace App\Infrastructure\Observability;

use App\Application\Observability\Contracts\DatabaseHealthPort;
use Illuminate\Support\Facades\DB;

final class EloquentDatabaseHealthAdapter implements DatabaseHealthPort
{
    public function isAvailable(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
