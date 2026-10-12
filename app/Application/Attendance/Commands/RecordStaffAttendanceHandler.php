<?php

namespace App\Application\Attendance\Commands;

use App\Application\Attendance\Results\RecordStaffAttendanceResult;
use App\Application\Attendance\Support\StaffRecordWriter;
use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\UnitOfWork;

final class RecordStaffAttendanceHandler implements CommandHandler
{
    public const COMMAND_NAME = 'RecordStaffAttendance';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly StaffRecordWriter $writer,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): RecordStaffAttendanceResult
    {
        assert($command instanceof RecordStaffAttendanceCommand);

        $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
        if ($cached !== null) {
            return RecordStaffAttendanceResult::fromIdempotency($cached);
        }

        $values = $this->writer->validatedValues($command);
        $data = $this->unitOfWork->transaction(fn (): array => $this->writer->write($command, $values));

        $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, $data);

        return RecordStaffAttendanceResult::success($data);
    }
}
