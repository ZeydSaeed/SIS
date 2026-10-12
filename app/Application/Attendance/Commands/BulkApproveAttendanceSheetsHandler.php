<?php

namespace App\Application\Attendance\Commands;

use App\Application\Attendance\Results\BulkApproveAttendanceSheetsResult;
use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Domain\Attendance\ValueObjects\SheetAction;
use App\Domain\Shared\Exceptions\SisDomainException;

/** Item-by-item approval (one transaction each) through the single review path; failures are reported per sheet. */
final class BulkApproveAttendanceSheetsHandler implements CommandHandler
{
    public function __construct(
        private readonly ReviewAttendanceSheetHandler $review,
    ) {}

    public function handle(Command $command): BulkApproveAttendanceSheetsResult
    {
        assert($command instanceof BulkApproveAttendanceSheetsCommand);

        $approved = [];
        $failed = [];
        foreach (array_slice($command->items, 0, BulkApproveAttendanceSheetsCommand::MAX_ITEMS) as $item) {
            $sessionId = (int) $item['session_id'];
            try {
                $this->review->handle(new ReviewAttendanceSheetCommand(
                    $command->schoolId, $command->academicYearId, $sessionId, (int) $item['version'], SheetAction::Approve, null,
                    $command->actorUserId, $command->idempotencyKey.'#'.$sessionId,
                ));
                $approved[] = $sessionId;
            } catch (SisDomainException $e) {
                $failed[] = ['session_id' => $sessionId, 'code' => $e->errorCode()];
            }
        }

        return BulkApproveAttendanceSheetsResult::success(['approved' => $approved, 'failed' => $failed]);
    }
}
