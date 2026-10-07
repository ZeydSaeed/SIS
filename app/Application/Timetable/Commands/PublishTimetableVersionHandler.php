<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Timetable\Results\PublishTimetableVersionResult;
use App\Application\Timetable\Support\EngineTransaction;
use App\Domain\Timetable\Repositories\TimetableVersionRepositoryInterface;
use App\Domain\Timetable\ValueObjects\TimetableVersionStatus;

final class PublishTimetableVersionHandler implements CommandHandler
{
    public function __construct(
        private readonly EngineTransaction $tx,
        private readonly TimetableVersionRepositoryInterface $versions,
    ) {}

    public function handle(Command $command): PublishTimetableVersionResult
    {
        assert($command instanceof PublishTimetableVersionCommand);
        $from = \DateTimeImmutable::createFromFormat('!Y-m-d', $command->effectiveFrom);
        if ($from === false) {
            return PublishTimetableVersionResult::failure('timetable.publish_date_invalid');
        }

        return $this->tx->run(PublishTimetableVersionResult::class, $command->idempotencyKey, 'PublishTimetableVersion', function () use ($command, $from): PublishTimetableVersionResult {
            $version = $this->versions->find($command->schoolId, $command->versionId);
            if ($version === null || ! TimetableVersionStatus::from($version['status'])->canPublish()) {
                return PublishTimetableVersionResult::failure('timetable.version_not_approved');
            }
            $date = $from->format('Y-m-d');
            $current = $this->versions->currentPublished($command->schoolId, $version['academic_year_id']);
            if ($current !== null && $current['effective_from'] >= $date) {
                return PublishTimetableVersionResult::failure('timetable.publish_date_before_current');
            }
            $superseded = $this->versions->supersedePublished($command->schoolId, $version['academic_year_id'], $command->versionId, $date);
            $this->versions->setStatus($command->schoolId, $command->versionId, TimetableVersionStatus::Approved->value, TimetableVersionStatus::Published->value, [
                'published_by' => $command->userId,
                'published_at' => (new \DateTimeImmutable)->format('Y-m-d H:i:sP'),
                'effective_from' => $date,
            ]);
            $this->tx->stage('version_published', $command->schoolId, $version['academic_year_id'], $command->versionId, $command->userId, ['effective_from' => $date, 'superseded' => $superseded]);

            return PublishTimetableVersionResult::success($command->versionId, ['effective_from' => $date, 'superseded' => $superseded]);
        });
    }
}
