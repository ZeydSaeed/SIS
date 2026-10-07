<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;

/**
 * «إرسال للاعتماد»: opens a workflow.approval_requests request for the version (decision D4 — the official
 * approval system, no parallel one). The school's active «timetable_version» flow is used; when the school has
 * none yet, a one-step flow for the «timetable_approver» role is created.
 */
final readonly class SubmitTimetableVersionCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $versionId,
        public ?int $userId,
        public ?string $idempotencyKey = null,
    ) {}
}
