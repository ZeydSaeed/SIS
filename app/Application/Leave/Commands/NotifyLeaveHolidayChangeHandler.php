<?php

namespace App\Application\Leave\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\UnitOfWork;
use App\Application\Leave\Contracts\LeaveReadRepositoryInterface;
use App\Application\Leave\Results\NotifyLeaveHolidayChangeResult;
use App\Application\Leave\Support\LeaveNotifier;
use App\Domain\Academic\Repositories\HolidayRepositoryInterface;

/**
 * Notice only: a holiday that was added, edited or taken out changes how many working days an open request counts, but
 * a request's duration and balance are never rewritten behind its owner's back. The leave managers are told which open
 * requests (draft, pending, returned) overlap the holiday so they can have them re-saved or re-submitted. A holiday that
 * no longer exists tells nobody; running it again adds nothing (an unread notice is not stacked).
 */
final class NotifyLeaveHolidayChangeHandler implements CommandHandler
{
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly HolidayRepositoryInterface $holidays,
        private readonly LeaveReadRepositoryInterface $read,
        private readonly LeaveNotifier $notifier,
    ) {}

    public function handle(Command $command): NotifyLeaveHolidayChangeResult
    {
        assert($command instanceof NotifyLeaveHolidayChangeCommand);
        $holiday = $this->holidays->findVisible($command->schoolId, $command->holidayId);
        $ids = $holiday === null ? [] : $this->read->openRequestIdsOverlapping($command->schoolId, substr($holiday->startDate, 0, 10), substr($holiday->endDate, 0, 10));
        $notified = $ids === [] ? 0 : $this->unitOfWork->transaction(fn (): int => $this->notifier->holidayChanged($command->schoolId, $ids));

        return NotifyLeaveHolidayChangeResult::success(['notified' => $notified]);
    }
}
