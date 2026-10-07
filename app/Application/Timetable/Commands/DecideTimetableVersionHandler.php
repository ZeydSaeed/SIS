<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Timetable\Results\DecideTimetableVersionResult;
use App\Application\Workflow\Commands\DecideApprovalRequestCommand;
use App\Application\Workflow\Commands\DecideApprovalRequestHandler;
use App\Domain\Timetable\Repositories\TimetableVersionRepositoryInterface;
use App\Domain\Timetable\ValueObjects\TimetableVersionStatus;

final class DecideTimetableVersionHandler implements CommandHandler
{
    public function __construct(
        private readonly TimetableVersionRepositoryInterface $versions,
        private readonly DecideApprovalRequestHandler $decide,
    ) {}

    public function handle(Command $command): DecideTimetableVersionResult
    {
        assert($command instanceof DecideTimetableVersionCommand);
        $version = $this->versions->find($command->schoolId, $command->versionId);
        if ($version === null || $version['status'] !== TimetableVersionStatus::Review->value || $version['approval_request_id'] === null) {
            return DecideTimetableVersionResult::failure('timetable.version_not_in_review');
        }
        $key = trim((string) $command->idempotencyKey);
        $result = $this->decide->handle(new DecideApprovalRequestCommand(
            $command->schoolId,
            $version['approval_request_id'],
            $command->decision,
            $command->actorUserId,
            ($key !== '' ? $key : bin2hex(random_bytes(8))).':workflow',
        ));
        if ($result->failed()) {
            return DecideTimetableVersionResult::failure(...$result->errors);
        }
        $after = $this->versions->find($command->schoolId, $command->versionId);

        return DecideTimetableVersionResult::success($command->versionId, ['status' => $after['status'] ?? null, 'approval_status' => $result->status]);
    }
}
