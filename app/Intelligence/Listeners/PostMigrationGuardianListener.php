<?php

namespace App\Intelligence\Listeners;

use App\Intelligence\Jobs\RunSchemaGuardianJob;
use Illuminate\Database\Events\MigrationsEnded;

class PostMigrationGuardianListener
{
    public function handle(MigrationsEnded $event): void
    {
        if (! config('intelligence.enabled', true)) {
            return;
        }

        RunSchemaGuardianJob::dispatch();
    }
}
