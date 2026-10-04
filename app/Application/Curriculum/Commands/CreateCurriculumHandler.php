<?php

namespace App\Application\Curriculum\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Curriculum\Results\CreateCurriculumResult;
use App\Domain\Curriculum\Contracts\SpecializationCatalogPort;
use App\Domain\Curriculum\Events\CurriculumCreated;
use App\Domain\Curriculum\Events\CurriculumSubjectLinked;
use App\Domain\Curriculum\Repositories\CurriculumRepositoryInterface;
use App\Domain\Curriculum\Repositories\SubjectRepositoryInterface;
use App\Domain\Curriculum\Services\CreateCurriculumGuard;
use App\Domain\Curriculum\Support\CurriculumIdempotencyGuard;

final class CreateCurriculumHandler implements CommandHandler
{
    private const COMMAND_NAME = 'CreateCurriculum';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly CurriculumRepositoryInterface $curricula,
        private readonly SubjectRepositoryInterface $subjects,
        private readonly CreateCurriculumGuard $guard,
        private readonly SpecializationCatalogPort $specializations,
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
            $command->departmentId,
        );
        if ($error !== null) {
            return CreateCurriculumResult::failure([$error]);
        }

        $subjectIds = array_values(array_unique(array_map(
            static fn (mixed $id): int => (int) $id,
            $command->subjectIds,
        )));
        foreach ($subjectIds as $subjectId) {
            if ($this->subjects->findActive($subjectId) === null) {
                return CreateCurriculumResult::failure(['curriculum.subject_not_found']);
            }
        }

        $id = $this->unitOfWork->transaction(function () use ($command, $key, $subjectIds): int {
            $createdAt = (new \DateTimeImmutable)->format(\DateTimeInterface::ATOM);
            $id = $this->curricula->create(
                $command->schoolId,
                $command->academicYearId,
                $command->gradeLevelId,
                $command->name,
                $command->specializationId,
                $createdAt,
                $command->departmentId,
            );

            if ($subjectIds !== []) {
                foreach ($subjectIds as $order => $subjectId) {
                    $snapshot = $this->subjects->findActive($subjectId);
                    $linkId = $this->curricula->linkSubject(
                        $command->schoolId,
                        $id,
                        $subjectId,
                        $snapshot?->creditHours,
                        true,
                        $order,
                        $createdAt,
                    );
                    $this->outbox->stage(new CurriculumSubjectLinked(
                        $linkId,
                        $id,
                        $subjectId,
                        new \DateTimeImmutable,
                    ));
                }
            } elseif ($command->specializationId !== null) {
                $order = 0;
                foreach ($this->specializations->listActiveSubjectTemplates(
                    $command->schoolId,
                    $command->specializationId,
                ) as $template) {
                    $linkId = $this->curricula->linkSubject(
                        $command->schoolId,
                        $id,
                        $template->subjectId,
                        $template->creditHours,
                        $template->isRequired,
                        $order,
                        $createdAt,
                    );
                    $this->outbox->stage(new CurriculumSubjectLinked(
                        $linkId,
                        $id,
                        $template->subjectId,
                        new \DateTimeImmutable,
                    ));
                    $order++;
                }
            }

            $this->outbox->stage(new CurriculumCreated($id, $command->schoolId, new \DateTimeImmutable));
            $this->idempotency->store($key, self::COMMAND_NAME, ['curriculum_id' => $id]);

            return $id;
        });

        return CreateCurriculumResult::success($id);
    }
}
