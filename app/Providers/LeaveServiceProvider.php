<?php

namespace App\Providers;

use App\Application\Attendance\Contracts\ApprovedLeaveSourceInterface;
use App\Application\Leave\Contracts\LeaveAttachmentStorageInterface;
use App\Application\Leave\Contracts\LeaveReadRepositoryInterface;
use App\Application\Leave\Contracts\LeaveStaffDirectoryInterface;
use App\Domain\Leave\Repositories\LeaveBalanceRepositoryInterface;
use App\Domain\Leave\Repositories\LeaveDelegationRepositoryInterface;
use App\Domain\Leave\Repositories\LeaveNotificationRepositoryInterface;
use App\Domain\Leave\Repositories\LeaveRequestRepositoryInterface;
use App\Domain\Leave\Repositories\LeaveTypeRepositoryInterface;
use App\Infrastructure\Events\HolidayChangedBridgeEvent;
use App\Infrastructure\Leave\DocumentsLeaveAttachmentStorage;
use App\Infrastructure\Leave\LeaveApprovedLeaveSource;
use App\Infrastructure\Persistence\Leave\EloquentLeaveBalanceRepository;
use App\Infrastructure\Persistence\Leave\EloquentLeaveDelegationRepository;
use App\Infrastructure\Persistence\Leave\EloquentLeaveNotificationRepository;
use App\Infrastructure\Persistence\Leave\EloquentLeaveReadRepository;
use App\Infrastructure\Persistence\Leave\EloquentLeaveRequestRepository;
use App\Infrastructure\Persistence\Leave\EloquentLeaveStaffDirectory;
use App\Infrastructure\Persistence\Leave\EloquentLeaveTypeRepository;
use App\Listeners\Leave\NotifyLeaveOfHolidayChange;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/** «الإجازات والغياب» — ports of the leave module bound to their PostgreSQL / Documents adapters. */
final class LeaveServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LeaveTypeRepositoryInterface::class, EloquentLeaveTypeRepository::class);
        $this->app->bind(LeaveRequestRepositoryInterface::class, EloquentLeaveRequestRepository::class);
        $this->app->bind(LeaveBalanceRepositoryInterface::class, EloquentLeaveBalanceRepository::class);
        $this->app->bind(LeaveNotificationRepositoryInterface::class, EloquentLeaveNotificationRepository::class);
        $this->app->bind(LeaveDelegationRepositoryInterface::class, EloquentLeaveDelegationRepository::class);
        $this->app->bind(LeaveReadRepositoryInterface::class, EloquentLeaveReadRepository::class);
        $this->app->bind(LeaveStaffDirectoryInterface::class, EloquentLeaveStaffDirectory::class);
        $this->app->bind(LeaveAttachmentStorageInterface::class, DocumentsLeaveAttachmentStorage::class);
        // Attendance reads approved teacher leave (staff day + lessons needing cover) through its own port.
        $this->app->bind(ApprovedLeaveSourceInterface::class, LeaveApprovedLeaveSource::class);
    }

    public function boot(): void
    {
        // A holiday changed (outbox, after commit) → the leave managers are told which open requests it touches.
        Event::listen(HolidayChangedBridgeEvent::class, NotifyLeaveOfHolidayChange::class);
    }
}
