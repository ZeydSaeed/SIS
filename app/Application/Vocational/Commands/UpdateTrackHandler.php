<?php

namespace App\Application\Vocational\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Vocational\Results\UpdateTrackResult;
use App\Domain\Vocational\Events\TrackUpdated;
use App\Domain\Vocational\Repositories\VocationalCatalogRepositoryInterface;
use App\Domain\Vocational\Support\VocationalIdempotencyGuard;

final class UpdateTrackHandler implements CommandHandler
{
    private const COMMAND_NAME = 'UpdateTrack';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly VocationalCatalogRepositoryInterface $catalog,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): UpdateTrackResult
    {
        assert($command instanceof UpdateTrackCommand);
        $key = VocationalIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return UpdateTrackResult::fromIdempotency((int) $cached['track_id']);
        }

        $at = now()->toIso8601String();
        $this->unitOfWork->transaction(function () use ($command, $key, $at): void {
            $this->catalog->updateTrack(
                $command->schoolId,
                $command->trackId,
                $command->code,
                $command->name,
                $at,
            );
            $this->outbox->stage(new TrackUpdated(
                $command->trackId,
                $command->schoolId,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, ['track_id' => $command->trackId]);
        });

        return UpdateTrackResult::success($command->trackId);
    }
}
