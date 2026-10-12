<?php

namespace App\Application\Attendance\Commands;

use App\Application\Attendance\Results\SaveAttendanceSheetResult;
use App\Application\Attendance\Support\SheetGate;
use App\Application\Attendance\Support\SheetMarkWriter;
use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\UnitOfWork;
use App\Domain\Attendance\Exceptions\AttendanceRuleViolation;
use App\Domain\Attendance\Repositories\AttendanceSheetRepositoryInterface;
use App\Domain\Attendance\ValueObjects\SheetEventType;

/**
 * Draft save: the version guard rejects a stale tab / an old retried request instead of overwriting newer marks;
 * a retry of the same request (same idempotency key) returns the first outcome.
 */
final class SaveAttendanceSheetHandler implements CommandHandler
{
    public const COMMAND_NAME = 'SaveAttendanceSheet';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly SheetGate $gate,
        private readonly SheetMarkWriter $writer,
        private readonly AttendanceSheetRepositoryInterface $sheets,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): SaveAttendanceSheetResult
    {
        assert($command instanceof SaveAttendanceSheetCommand);

        $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
        if ($cached !== null) {
            return SaveAttendanceSheetResult::fromIdempotency($cached);
        }

        $data = $this->unitOfWork->transaction(function () use ($command): array {
            $context = $this->gate->forTeacherWork($command->sessionId, $command->schoolId, $command->academicYearId, $command->expectedVersion, $command->actor);
            $written = $this->writer->write($context, $command->marks);
            if (! $this->sheets->bumpVersion($command->sessionId, $command->expectedVersion)) {
                throw AttendanceRuleViolation::versionConflict($command->sessionId, $command->expectedVersion, -1);
            }
            $version = $command->expectedVersion + 1;
            $status = $context->sheet->sheetStatus;
            $this->sheets->appendEvent($command->schoolId, $command->academicYearId, $command->sessionId, SheetEventType::DraftSaved, $status, $status, $version, $command->actor->userId, null, $written['counts']);

            return ['session_id' => $command->sessionId, 'version' => $version, 'counts' => $written['counts'], 'normalized' => $written['normalized']];
        });

        $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, $data);

        return SaveAttendanceSheetResult::success($data, $data['normalized'] > 0 ? ['attendance.late_normalized'] : []);
    }
}
