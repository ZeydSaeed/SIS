<?php

namespace App\Application\Attendance\Commands;

use App\Application\Attendance\Contracts\AttendanceReportReadInterface;
use App\Application\Attendance\Contracts\ReportExportDispatcherInterface;
use App\Application\Attendance\Results\RequestAttendanceReportExportResult;
use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Domain\Attendance\Exceptions\AttendanceRuleViolation;
use App\Domain\Attendance\Repositories\AttendanceReportExportRepositoryInterface;

final class RequestAttendanceReportExportHandler implements CommandHandler
{
    public const COMMAND_NAME = 'RequestAttendanceReportExport';

    public function __construct(
        private readonly AttendanceReportExportRepositoryInterface $exports,
        private readonly ReportExportDispatcherInterface $dispatcher,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): RequestAttendanceReportExportResult
    {
        assert($command instanceof RequestAttendanceReportExportCommand);

        $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
        if ($cached !== null) {
            return RequestAttendanceReportExportResult::fromIdempotency($cached);
        }
        if (! in_array($command->reportType, AttendanceReportReadInterface::TYPES, true) || ! in_array($command->format, [1, 2], true)) {
            throw AttendanceRuleViolation::reportTypeInvalid($command->reportType);
        }

        $id = $this->exports->create($command->schoolId, $command->academicYearId, $command->requestedBy, $command->reportType, $command->format, $command->filters);
        $this->dispatcher->dispatch($id, $command->schoolId);

        $data = ['export_id' => $id];
        $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, $data);

        return RequestAttendanceReportExportResult::success($data);
    }
}
