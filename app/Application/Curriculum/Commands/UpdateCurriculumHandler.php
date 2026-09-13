<?php

namespace App\Application\Curriculum\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Curriculum\Results\UpdateCurriculumResult;
use App\Domain\Curriculum\Events\CurriculumUpdated;
use App\Domain\Curriculum\Repositories\CurriculumRepositoryInterface;
use App\Domain\Curriculum\Services\UpdateCurriculumGuard;
use App\Domain\Curriculum\Support\CurriculumIdempotencyGuard;

final class UpdateCurriculumHandler implements CommandHandler
{
    private const COMMAND_NAME = 'UpdateCurriculum';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly CurriculumRepositoryInterface $curricula,
        private readonly UpdateCurriculumGuard $guard,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): UpdateCurriculumResult
    {
        assert($command instanceof UpdateCurriculumCommand);
        $key = CurriculumIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            /** @var array{name?: string, specialization_id?: ?int} $fields */
            $fields = is_array($cached['fields'] ?? null) ? $cached['fields'] : [];

            return UpdateCurriculumResult::fromIdempotency((int) $cached['curriculum_id'], $fields);
        }

        $error = $this->guard->rejectionCode(
            $command->schoolId,
            $command->curriculumId,
            $command->fields,
        );
        if ($error !== null) {
            return UpdateCurriculumResult::failure([$error]);
        }

        $this->unitOfWork->transaction(function () use ($command, $key): void {
            $this->curricula->updateActive(
                $command->schoolId,
                $command->curriculumId,
                $command->fields,
                (new \DateTimeImmutable)->format(\DateTimeInterface::ATOM),
            );
            $this->outbox->stage(new CurriculumUpdated(
                $command->curriculumId,
                $command->schoolId,
                $command->fields,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, [
                'curriculum_id' => $command->curriculumId,
                'fields' => $command->fields,
            ]);
        });

        return UpdateCurriculumResult::success($command->curriculumId, $command->fields);
    }
}
