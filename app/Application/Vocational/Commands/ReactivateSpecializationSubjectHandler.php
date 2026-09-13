<?php

namespace App\Application\Vocational\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Vocational\Results\ReactivateSpecializationSubjectResult;
use App\Domain\Vocational\Events\SpecializationSubjectReactivated;
use App\Domain\Vocational\Repositories\VocationalCatalogRepositoryInterface;
use App\Domain\Vocational\Support\VocationalIdempotencyGuard;

final class ReactivateSpecializationSubjectHandler implements CommandHandler
{
    private const COMMAND_NAME = 'ReactivateSpecializationSubject';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly VocationalCatalogRepositoryInterface $catalog,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): ReactivateSpecializationSubjectResult
    {
        assert($command instanceof ReactivateSpecializationSubjectCommand);
        $key = VocationalIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return ReactivateSpecializationSubjectResult::fromIdempotency((int) $cached['link_id']);
        }

        $this->unitOfWork->transaction(function () use ($command, $key): void {
            $this->catalog->reactivateSpecializationSubject($command->schoolId, $command->linkId);
            $this->outbox->stage(new SpecializationSubjectReactivated(
                $command->linkId,
                $command->schoolId,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, ['link_id' => $command->linkId]);
        });

        return ReactivateSpecializationSubjectResult::success($command->linkId);
    }
}
