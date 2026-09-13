<?php

namespace App\Application\Curriculum\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Curriculum\Results\UpdateCurriculumSpecializationResult;
use App\Domain\Curriculum\Events\CurriculumSpecializationUpdated;
use App\Domain\Curriculum\Repositories\CurriculumRepositoryInterface;
use App\Domain\Curriculum\Services\UpdateCurriculumSpecializationGuard;
use App\Domain\Curriculum\Support\CurriculumIdempotencyGuard;

final class UpdateCurriculumSpecializationHandler implements CommandHandler
{
    private const COMMAND_NAME = 'UpdateCurriculumSpecialization';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly CurriculumRepositoryInterface $curricula,
        private readonly UpdateCurriculumSpecializationGuard $guard,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): UpdateCurriculumSpecializationResult
    {
        assert($command instanceof UpdateCurriculumSpecializationCommand);
        $key = CurriculumIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return UpdateCurriculumSpecializationResult::fromIdempotency(
                (int) $cached['curriculum_id'],
                array_key_exists('specialization_id', $cached) && $cached['specialization_id'] !== null
                    ? (int) $cached['specialization_id']
                    : null,
            );
        }

        $error = $this->guard->rejectionCode(
            $command->schoolId,
            $command->curriculumId,
            $command->specializationId,
        );
        if ($error !== null) {
            return UpdateCurriculumSpecializationResult::failure([$error]);
        }

        $this->unitOfWork->transaction(function () use ($command, $key): void {
            $this->curricula->updateSpecialization(
                $command->schoolId,
                $command->curriculumId,
                $command->specializationId,
                (new \DateTimeImmutable)->format(\DateTimeInterface::ATOM),
            );
            $this->outbox->stage(new CurriculumSpecializationUpdated(
                $command->curriculumId,
                $command->schoolId,
                $command->specializationId,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, [
                'curriculum_id' => $command->curriculumId,
                'specialization_id' => $command->specializationId,
            ]);
        });

        return UpdateCurriculumSpecializationResult::success(
            $command->curriculumId,
            $command->specializationId,
        );
    }
}
