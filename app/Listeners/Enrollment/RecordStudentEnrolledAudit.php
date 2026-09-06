<?php

namespace App\Listeners\Enrollment;

use App\Infrastructure\Events\StudentEnrolledBridgeEvent;
use Illuminate\Support\Facades\Log;

final class RecordStudentEnrolledAudit
{
    public function handle(StudentEnrolledBridgeEvent $event): void
    {
        Log::info('student.enrolled', $event->domainEvent->payload());
    }
}
