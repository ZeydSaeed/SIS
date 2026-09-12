<?php

namespace App\Application\Vocational\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Vocational\Results\CreateTrackResult;
use App\Domain\Vocational\Events\TrackCreated;
use App\Domain\Vocational\Repositories\VocationalCatalogRepositoryInterface;
use App\Domain\Vocational\Support\VocationalIdempotencyGuard;

final class CreateTrackHandler implements CommandHandler
{
    private const COMMAND_NAME = 'CreateTrack';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly VocationalCatalogRepositoryInterface $catalog,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): CreateTrackResult
    {
        assert($command instanceof CreateTrackCommand);
        $key = VocationalIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return CreateTrackResult::fromIdempotency((int) $cached['track_id']);
        }

        $at = now()->toIso8601String();
        $id = $this->unitOfWork->transaction(function () use ($command, $key, $at): int {
            $id = $this->catalog->createTrack(
                $command->schoolId,
                $command->specializationId,
                $command->code,
                $command->name,
                $at,
            );
            $this->outbox->stage(new TrackCreated(
                $id,
                $command->schoolId,
                $command->specializationId,
                $command->code,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, ['track_id' => $id]);

            return $id;
        });

        return CreateTrackResult::success($id);
    }
}
