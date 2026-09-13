<?php

namespace App\Application\Curriculum\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Curriculum\Results\DeactivateCurriculumResult;
use App\Domain\Curriculum\Events\CurriculumDeactivated;
use App\Domain\Curriculum\Repositories\CurriculumRepositoryInterface;
use App\Domain\Curriculum\Support\CurriculumIdempotencyGuard;

final class DeactivateCurriculumHandler implements CommandHandler
{
    private const COMMAND_NAME = 'DeactivateCurriculum';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly CurriculumRepositoryInterface $curricula,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): DeactivateCurriculumResult
    {
        assert($command instanceof DeactivateCurriculumCommand);
        $key = CurriculumIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return DeactivateCurriculumResult::fromIdempotency((int) $cached['curriculum_id']);
        }

        if ($this->curricula->findActiveInSchool($command->schoolId, $command->curriculumId) === null) {
            return DeactivateCurriculumResult::failure(['curriculum.curriculum_not_found']);
        }

        $this->unitOfWork->transaction(function () use ($command, $key): void {
            $this->curricula->deactivate($command->schoolId, $command->curriculumId);
            $this->outbox->stage(new CurriculumDeactivated(
                $command->curriculumId,
                $command->schoolId,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, [
                'curriculum_id' => $command->curriculumId,
            ]);
        });

        return DeactivateCurriculumResult::success($command->curriculumId);
    }
}
