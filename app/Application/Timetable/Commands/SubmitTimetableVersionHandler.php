<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Timetable\Results\SubmitTimetableVersionResult;
use App\Application\Timetable\Support\EngineTransaction;
use App\Application\Workflow\Commands\CreateApprovalRequestCommand;
use App\Application\Workflow\Commands\CreateApprovalRequestHandler;
use App\Domain\Timetable\Repositories\TimetableVersionRepositoryInterface;
use App\Domain\Timetable\ValueObjects\TimetableVersionStatus;
use App\Domain\Workflow\Repositories\ApprovalFlowRepositoryInterface;
use App\Domain\Workflow\Repositories\ApprovalRequestRepositoryInterface;
use App\Domain\Workflow\ValueObjects\ApprovalRequestStatus;

final class SubmitTimetableVersionHandler implements CommandHandler
{
    public const ENTITY = 'timetable_version';

    public const APPROVER_ROLE = 'timetable_approver';

    public function __construct(
        private readonly EngineTransaction $tx,
        private readonly TimetableVersionRepositoryInterface $versions,
        private readonly ApprovalFlowRepositoryInterface $flows,
        private readonly ApprovalRequestRepositoryInterface $requests,
        private readonly CreateApprovalRequestHandler $createApproval,
    ) {}

    public function handle(Command $command): SubmitTimetableVersionResult
    {
        assert($command instanceof SubmitTimetableVersionCommand);
        $version = $this->versions->find($command->schoolId, $command->versionId);
        if ($version === null) {
            return SubmitTimetableVersionResult::failure('timetable.version_not_found');
        }
        $status = TimetableVersionStatus::from($version['status']);
        if (! $status->canSubmit() && ! $this->reviewWithdrawn($command->schoolId, $version)) {
            return SubmitTimetableVersionResult::failure('timetable.version_not_submittable');
        }
        if ((int) ($version['quality']['errors'] ?? 0) > 0) {
            return SubmitTimetableVersionResult::failure('timetable.version_has_errors');
        }

        return $this->tx->run(SubmitTimetableVersionResult::class, $command->idempotencyKey, 'SubmitTimetableVersion', function () use ($command, $version, $status): SubmitTimetableVersionResult {
            $flow = $this->flows->findActiveByEntityType($command->schoolId, self::ENTITY);
            $flowId = $flow?->id ?? $this->flows->create($command->schoolId, self::ENTITY, 'اعتماد الجدول الدراسي', [['step' => 1, 'role' => self::APPROVER_ROLE]], true, (new \DateTimeImmutable)->format('Y-m-d H:i:s'));
            $approval = $this->createApproval->handle(new CreateApprovalRequestCommand(
                $command->schoolId, $flowId, self::ENTITY, $command->versionId, $command->userId,
                ($command->idempotencyKey ?? bin2hex(random_bytes(8))).':workflow',
            ));
            if ($approval->failed()) {
                return SubmitTimetableVersionResult::failure(...$approval->errors);
            }
            $this->versions->setStatus($command->schoolId, $command->versionId, $status->value, TimetableVersionStatus::Review->value, ['approval_request_id' => $approval->requestId]);
            $this->tx->stage('version_submitted', $command->schoolId, $version['academic_year_id'], $command->versionId, $command->userId, ['approval_request_id' => $approval->requestId]);

            return SubmitTimetableVersionResult::success($command->versionId, ['approval_request_id' => $approval->requestId]);
        });
    }

    /** A version left in review after its approval request was cancelled may be submitted again. */
    private function reviewWithdrawn(int $schoolId, array $version): bool
    {
        if ($version['status'] !== TimetableVersionStatus::Review->value || $version['approval_request_id'] === null) {
            return false;
        }
        $request = $this->requests->findById($schoolId, $version['approval_request_id']);

        return $request === null || $request->status === ApprovalRequestStatus::Cancelled;
    }
}
