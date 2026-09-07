<?php

namespace App\Optimization\SelfHealing;

use Illuminate\Support\Facades\Log;

final class SelfHealingEventLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function emit(string $event, array $context = []): void
    {
        Log::info('SELF_HEALING:'.$event, array_merge([
            'event' => $event,
            'timestamp' => now()->toIso8601String(),
        ], $context));
    }
}
