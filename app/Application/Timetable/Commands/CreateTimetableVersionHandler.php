<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Timetable\Results\CreateTimetableVersionResult;
use App\Application\Timetable\Support\EngineTransaction;
use App\Application\Timetable\Support\TimetableBoardLoader;
use App\Domain\Timetable\Repositories\TimetableVersionRepositoryInterface;
use App\Domain\Timetable\Services\TimetableAuditor;
use App\Domain\Timetable\Services\TimetableFingerprint;
use App\Domain\Timetable\Services\TimetableQualityScorer;
use App\Domain\Timetable\Testing\PlacementChecks;
use App\Domain\Timetable\Testing\RemedyFinder;
use App\Domain\Timetable\Testing\TestIssue;

final class CreateTimetableVersionHandler implements CommandHandler
{
    public function __construct(
        private readonly EngineTransaction $tx,
        private readonly TimetableBoardLoader $boards,
        private readonly TimetableAuditor $auditor,
        private readonly TimetableQualityScorer $scorer,
        private readonly TimetableFingerprint $fingerprints,
        private readonly TimetableVersionRepositoryInterface $versions,
        private readonly PlacementChecks $placements,
    ) {}

    public function handle(Command $command): CreateTimetableVersionResult
    {
        assert($command instanceof CreateTimetableVersionCommand);
        $name = trim($command->name);
        if ($name === '' || mb_strlen($name) > 150) {
            return CreateTimetableVersionResult::failure('timetable.version_name_invalid');
        }
        $board = $this->boards->load($command->schoolId, $command->academicYearId);
        if ($board->schedules === []) {
            return CreateTimetableVersionResult::failure('timetable.version_empty');
        }
        // The approval gate (SubmitTimetableVersion refuses a version with errors) counts the grid errors «اختبار الجدول»
        // reports as well: a room taken twice in a slot or smaller than the class it holds is as much an error as a
        // teacher booked twice.
        $roomErrors = array_values(array_filter(
            $this->placements->run($board, new RemedyFinder($board), []),
            static fn (array $issue): bool => $issue['severity'] === TestIssue::ERROR,
        ));
        $quality = $this->scorer->score($board, [...$this->auditor->audit($board), ...$roomErrors]);
        $fingerprint = $this->fingerprints->of($board);

        return $this->tx->run(CreateTimetableVersionResult::class, $command->idempotencyKey, 'CreateTimetableVersion', function () use ($command, $name, $quality, $fingerprint): CreateTimetableVersionResult {
            $parent = $this->versions->currentPublished($command->schoolId, $command->academicYearId);
            $id = $this->versions->snapshotWorkingGrid($command->schoolId, $command->academicYearId, $name, $command->reason, $fingerprint, $quality, $parent['id'] ?? null, $command->generationRunId, $command->userId);
            $this->tx->stage('version_created', $command->schoolId, $command->academicYearId, $id, $command->userId, ['quality' => $quality['overall'], 'errors' => $quality['errors']]);

            return CreateTimetableVersionResult::success($id, ['quality' => $quality]);
        });
    }
}
