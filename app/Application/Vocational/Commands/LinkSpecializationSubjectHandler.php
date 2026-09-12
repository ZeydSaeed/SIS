<?php

namespace App\Application\Vocational\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Vocational\Results\LinkSpecializationSubjectResult;
use App\Domain\Vocational\Events\SpecializationSubjectLinked;
use App\Domain\Vocational\Repositories\VocationalCatalogRepositoryInterface;
use App\Domain\Vocational\Support\VocationalIdempotencyGuard;

final class LinkSpecializationSubjectHandler implements CommandHandler
{
    private const COMMAND_NAME = 'LinkSpecializationSubject';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly VocationalCatalogRepositoryInterface $catalog,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): LinkSpecializationSubjectResult
    {
        assert($command instanceof LinkSpecializationSubjectCommand);
        $key = VocationalIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return LinkSpecializationSubjectResult::fromIdempotency((int) $cached['link_id']);
        }

        $id = $this->unitOfWork->transaction(function () use ($command, $key): int {
            $id = $this->catalog->linkSpecializationSubject(
                $command->schoolId,
                $command->specializationId,
                $command->subjectId,
                $command->isRequired,
                $command->creditHours,
            );
            $this->outbox->stage(new SpecializationSubjectLinked(
                $id,
                $command->schoolId,
                $command->specializationId,
                $command->subjectId,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, ['link_id' => $id]);

            return $id;
        });

        return LinkSpecializationSubjectResult::success($id);
    }
}
