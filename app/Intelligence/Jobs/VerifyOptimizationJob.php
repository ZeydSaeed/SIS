<?php

namespace App\Intelligence\Jobs;

use App\Intelligence\Models\OptimizationEvent;
use App\Intelligence\Optimization\VerificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class VerifyOptimizationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $optimizationEventId,
    ) {}

    public function handle(VerificationService $verificationService): void
    {
        $event = OptimizationEvent::query()->find($this->optimizationEventId);

        if ($event === null) {
            return;
        }

        $verificationService->verifyOptimizationEvent($event);
    }
}
