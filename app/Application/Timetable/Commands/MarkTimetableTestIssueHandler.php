<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Timetable\Results\MarkTimetableTestIssueResult;
use App\Application\Timetable\Support\EngineTransaction;
use App\Domain\Timetable\Repositories\TimetableTestMarkRepositoryInterface;

final class MarkTimetableTestIssueHandler implements CommandHandler
{
    public function __construct(
        private readonly EngineTransaction $tx,
        private readonly TimetableTestMarkRepositoryInterface $marks,
    ) {}

    public function handle(Command $command): MarkTimetableTestIssueResult
    {
        assert($command instanceof MarkTimetableTestIssueCommand);
        $key = trim($command->issueKey);
        if ($key === '' || mb_strlen($key) > 200) {
            return MarkTimetableTestIssueResult::failure('timetable.test_issue_invalid');
        }
        if ($command->mark !== null && ! in_array($command->mark, [TimetableTestMarkRepositoryInterface::IGNORE, TimetableTestMarkRepositoryInterface::REVIEW], true)) {
            return MarkTimetableTestIssueResult::failure('timetable.test_mark_invalid');
        }

        return $this->tx->run(MarkTimetableTestIssueResult::class, $command->idempotencyKey, 'MarkTimetableTestIssue', function () use ($command, $key): MarkTimetableTestIssueResult {
            $note = $command->note === null ? null : mb_substr(trim($command->note), 0, 255);
            $this->marks->set($command->schoolId, $command->academicYearId, $key, $command->mark, $note === '' ? null : $note, $command->userId, (new \DateTimeImmutable)->format('Y-m-d H:i:s'));
            $this->tx->stage('test_issue_marked', $command->schoolId, $command->academicYearId, null, $command->userId, ['issue_key' => $key, 'mark' => $command->mark]);

            return MarkTimetableTestIssueResult::success(0);
        });
    }
}
