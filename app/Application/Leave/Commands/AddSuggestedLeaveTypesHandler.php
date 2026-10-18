<?php

namespace App\Application\Leave\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Leave\Results\AddSuggestedLeaveTypesResult;
use App\Application\Leave\Support\LeaveTransaction;
use App\Domain\Leave\Repositories\LeaveTypeRepositoryInterface;
use App\Domain\Leave\Services\SuggestedLeaveTypes;

final class AddSuggestedLeaveTypesHandler implements CommandHandler
{
    public const COMMAND_NAME = 'Leave.AddSuggestedLeaveTypes';

    public function __construct(
        private readonly LeaveTransaction $tx,
        private readonly LeaveTypeRepositoryInterface $types,
    ) {}

    public function handle(Command $command): AddSuggestedLeaveTypesResult
    {
        assert($command instanceof AddSuggestedLeaveTypesCommand);

        return $this->tx->run(AddSuggestedLeaveTypesResult::class, $command->idempotencyKey, self::COMMAND_NAME, function () use ($command): array {
            $at = (new \DateTimeImmutable)->format('Y-m-d H:i:s');
            $added = 0;
            foreach (SuggestedLeaveTypes::all() as $code => [$fields, $terms]) {
                if ($this->types->codeTaken($command->schoolId, $code)) {
                    continue;
                }
                $id = $this->types->create($command->schoolId, $code, $fields, $command->actorUserId, $at);
                $this->types->publishPolicy($command->schoolId, $id, $terms, substr($at, 0, 10), $command->actorUserId);
                $added++;
            }

            return [
                'data' => ['added' => $added],
                'event' => LeaveTransaction::event('school', $command->schoolId, $command->schoolId, 'suggested_types_added', $command->actorUserId, ['added' => $added]),
            ];
        });
    }
}
