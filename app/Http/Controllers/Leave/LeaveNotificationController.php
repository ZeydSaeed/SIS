<?php

namespace App\Http\Controllers\Leave;

use App\Application\Leave\Commands\MarkLeaveNotificationsReadCommand;
use App\Application\Leave\Commands\MarkLeaveNotificationsReadHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\MarkLeaveNotificationsReadRequest;
use App\Http\Support\LeaveRequestContext;
use Illuminate\Http\RedirectResponse;

/** «الإشعارات» writes — a user marks only their own notifications read. */
final class LeaveNotificationController extends Controller
{
    public function __construct(
        private readonly LeaveRequestContext $context,
    ) {}

    public function read(MarkLeaveNotificationsReadRequest $request, MarkLeaveNotificationsReadHandler $handler): RedirectResponse
    {
        $ids = $request->validated('ids');
        $handler->handle(new MarkLeaveNotificationsReadCommand(
            $this->context->schoolId(),
            $this->context->userId($request),
            $ids === null ? null : array_map('intval', $ids),
            trim((string) $request->header('X-Idempotency-Key')),
        ));

        return back();
    }
}
