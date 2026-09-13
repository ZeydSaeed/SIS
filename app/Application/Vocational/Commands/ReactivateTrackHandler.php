<?php

namespace App\Application\Vocational\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Vocational\Results\ReactivateTrackResult;
use App\Domain\Vocational\Events\TrackReactivated;
use App\Domain\Vocational\Repositories\VocationalCatalogRepositoryInterface;
use App\Domain\Vocational\Support\VocationalIdempotencyGuard;

final class ReactivateTrackHandler implements CommandHandler
{
    private const COMMAND_NAME = 'ReactivateTrack';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly VocationalCatalogRepositoryInterface $catalog,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): ReactivateTrackResult
    {
        assert($command instanceof ReactivateTrackCommand);
        $key = VocationalIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return ReactivateTrackResult::fromIdempotency((int) $cached['track_id']);
        }

        $at = now()->toIso8601String();
        $this->unitOfWork->transaction(function () use ($command, $key, $at): void {
            $this->catalog->reactivateTrack($command->schoolId, $command->trackId, $at);
            $this->outbox->stage(new TrackReactivated(
                $command->trackId,
                $command->schoolId,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, ['track_id' => $command->trackId]);
        });

        return ReactivateTrackResult::success($command->trackId);
    }
}
