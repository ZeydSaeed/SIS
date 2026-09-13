<?php

namespace App\Application\Curriculum\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Curriculum\Results\CreateCurriculumResult;
use App\Domain\Curriculum\Events\CurriculumCreated;
use App\Domain\Curriculum\Repositories\CurriculumRepositoryInterface;
use App\Domain\Curriculum\Services\CreateCurriculumGuard;
use App\Domain\Curriculum\Support\CurriculumIdempotencyGuard;

final class CreateCurriculumHandler implements CommandHandler
{
    private const COMMAND_NAME = 'CreateCurriculum';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly CurriculumRepositoryInterface $curricula,
        private readonly CreateCurriculumGuard $guard,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): CreateCurriculumResult
    {
        assert($command instanceof CreateCurriculumCommand);
        $key = CurriculumIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return CreateCurriculumResult::fromIdempotency((int) $cached['curriculum_id']);
        }

        $error = $this->guard->rejectionCode(
            $command->schoolId,
            $command->academicYearId,
            $command->gradeLevelId,
            $command->name,
            $command->specializationId,
        );
        if ($error !== null) {
            return CreateCurriculumResult::failure([$error]);
        }

        $id = $this->unitOfWork->transaction(function () use ($command, $key): int {
            $id = $this->curricula->create(
                $command->schoolId,
                $command->academicYearId,
                $command->gradeLevelId,
                $command->name,
                $command->specializationId,
                (new \DateTimeImmutable)->format(\DateTimeInterface::ATOM),
            );
            $this->outbox->stage(new CurriculumCreated($id, $command->schoolId, new \DateTimeImmutable));
            $this->idempotency->store($key, self::COMMAND_NAME, ['curriculum_id' => $id]);

            return $id;
        });

        return CreateCurriculumResult::success($id);
    }
}
