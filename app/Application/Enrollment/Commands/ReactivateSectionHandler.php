<?php

namespace App\Application\Enrollment\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Enrollment\Results\ReactivateSectionResult;
use App\Domain\Enrollment\Events\SectionReactivated;
use App\Domain\Enrollment\Exceptions\EnrollmentStructureNotFoundException;
use App\Domain\Enrollment\Repositories\EnrollmentStructureRepositoryInterface;
use App\Domain\Enrollment\Support\EnrollmentIdempotencyGuard;

final class ReactivateSectionHandler implements CommandHandler
{
    private const COMMAND_NAME = 'ReactivateSection';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly EnrollmentStructureRepositoryInterface $structure,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): ReactivateSectionResult
    {
        assert($command instanceof ReactivateSectionCommand);
        $key = EnrollmentIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return ReactivateSectionResult::fromIdempotency((int) $cached['section_id']);
        }

        $section = $this->structure->findSection($command->schoolId, $command->sectionId);
        if ($section === null) {
            throw EnrollmentStructureNotFoundException::forSection($command->sectionId);
        }

        $at = now()->toIso8601String();
        $this->unitOfWork->transaction(function () use ($command, $key, $at, $section): void {
            $this->structure->reactivateSection($command->schoolId, $command->sectionId, $at);
            $this->outbox->stage(new SectionReactivated(
                $command->sectionId,
                $section->classId,
                $command->schoolId,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, ['section_id' => $command->sectionId]);
        });

        return ReactivateSectionResult::success($command->sectionId);
    }
}
