<?php

namespace App\Intelligence\Jobs;

use App\Intelligence\Guardian\SchemaGuardian;
use App\Intelligence\Models\Detection;
use App\Intelligence\Support\CorrelationContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunSchemaGuardianJob implements ShouldQueue
{
    use Queueable;

    public function handle(SchemaGuardian $schemaGuardian): void
    {
        if (! config('intelligence.enabled', true)) {
            return;
        }

        CorrelationContext::reset();

        foreach ($schemaGuardian->validate() as $finding) {
            Detection::query()->create([
                'detection_code' => $finding['code'].'-'.now()->format('YmdHis'),
                'rule_id' => 'NORM-001',
                'risk_tier' => 3,
                'severity' => $finding['severity'],
                'schema_name' => isset($finding['table']) ? explode('.', $finding['table'])[0] : null,
                'table_name' => isset($finding['table']) ? explode('.', $finding['table'])[1] ?? null : null,
                'title' => $finding['message'],
                'diagnosis' => $finding['message'],
                'evidence' => $finding,
                'status' => 'open',
                'correlation_id' => CorrelationContext::id(),
                'detected_at' => now(),
            ]);
        }
    }
}
