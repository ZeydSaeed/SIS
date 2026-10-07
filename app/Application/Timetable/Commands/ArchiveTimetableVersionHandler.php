<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Timetable\Results\ArchiveTimetableVersionResult;
use App\Application\Timetable\Support\EngineTransaction;
use App\Domain\Timetable\Repositories\TimetableVersionRepositoryInterface;
use App\Domain\Timetable\ValueObjects\TimetableVersionStatus;

final class ArchiveTimetableVersionHandler implements CommandHandler
{
    public function __construct(
        private readonly EngineTransaction $tx,
        private readonly TimetableVersionRepositoryInterface $versions,
    ) {}

    public function handle(Command $command): ArchiveTimetableVersionResult
    {
        assert($command instanceof ArchiveTimetableVersionCommand);

        return $this->tx->run(ArchiveTimetableVersionResult::class, $command->idempotencyKey, 'ArchiveTimetableVersion', function () use ($command): ArchiveTimetableVersionResult {
            $version = $this->versions->find($command->schoolId, $command->versionId);
            if ($version === null || ! TimetableVersionStatus::from($version['status'])->canArchive()) {
                return ArchiveTimetableVersionResult::failure('timetable.version_not_archivable');
            }
            $this->versions->setStatus($command->schoolId, $command->versionId, $version['status'], TimetableVersionStatus::Archived->value);
            $this->tx->stage('version_archived', $command->schoolId, $version['academic_year_id'], $command->versionId, $command->userId);

            return ArchiveTimetableVersionResult::success($command->versionId);
        });
    }
}
