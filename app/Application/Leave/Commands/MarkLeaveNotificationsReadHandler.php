<?php

namespace App\Application\Leave\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Leave\Results\MarkLeaveNotificationsReadResult;
use App\Application\Leave\Support\LeaveTransaction;
use App\Domain\Leave\Repositories\LeaveNotificationRepositoryInterface;

final class MarkLeaveNotificationsReadHandler implements CommandHandler
{
    public const COMMAND_NAME = 'Leave.MarkLeaveNotificationsRead';

    public function __construct(
        private readonly LeaveTransaction $tx,
        private readonly LeaveNotificationRepositoryInterface $notifications,
    ) {}

    public function handle(Command $command): MarkLeaveNotificationsReadResult
    {
        assert($command instanceof MarkLeaveNotificationsReadCommand);

        return $this->tx->run(MarkLeaveNotificationsReadResult::class, $command->idempotencyKey, self::COMMAND_NAME, function () use ($command): array {
            $marked = $this->notifications->markRead($command->schoolId, $command->userId, $command->ids, (new \DateTimeImmutable)->format('Y-m-d H:i:s'));

            // No outbox event: reading a notification changes nothing another module reacts to.
            return ['data' => ['marked' => $marked], 'event' => null];
        });
    }
}
