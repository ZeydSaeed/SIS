<?php

namespace App\Infrastructure\Jobs;

use App\Domain\Enrollment\Events\StudentEnrolled;
use App\Infrastructure\Events\StudentEnrolledBridgeEvent;
use App\Infrastructure\Persistence\Outbox\EloquentOutboxRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

final class ProcessOutboxJob implements ShouldQueue
{
    use Queueable;

    public function handle(EloquentOutboxRepository $outbox): void
    {
        foreach ($outbox->fetchUnprocessed() as $message) {
            try {
                $event = $outbox->rehydrateEvent($message->event_type, $message->payload);

                if ($event === null) {
                    Log::warning('outbox.unknown_event', ['type' => $message->event_type]);
                    $outbox->markProcessed((int) $message->id);

                    continue;
                }

                if ($event instanceof StudentEnrolled) {
                    Event::dispatch(new StudentEnrolledBridgeEvent($event));
                }

                $outbox->markProcessed((int) $message->id);
            } catch (\Throwable $e) {
                $outbox->incrementAttempts((int) $message->id);
                Log::error('outbox.process_failed', [
                    'id' => $message->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
