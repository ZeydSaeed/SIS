<?php

namespace App\Application\Exams\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\UnitOfWork;
use App\Application\Exams\Contracts\ExamAdministrationAuthorityPort;
use App\Application\Exams\Results\SaveExamTypeWeightVersionResult;
use App\Domain\Exams\Exceptions\ExamTypeException;
use App\Domain\Exams\Exceptions\ExamValidationException;
use App\Domain\Exams\Repositories\ExamTypeRepositoryInterface;
use App\Domain\Exams\Repositories\ExamTypeWeightVersionRepositoryInterface;
use App\Domain\Exams\Services\ExamTypeRules;
use App\Domain\Exams\ValueObjects\ExamAdministrationAction;

final class SaveExamTypeWeightVersionHandler implements CommandHandler
{
    private const COMMAND_NAME = 'Exams.SaveExamTypeWeightVersion';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly ExamAdministrationAuthorityPort $authority,
        private readonly ExamTypeRepositoryInterface $types,
        private readonly ExamTypeWeightVersionRepositoryInterface $versions,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): SaveExamTypeWeightVersionResult
    {
        assert($command instanceof SaveExamTypeWeightVersionCommand);

        if ($command->idempotencyKey === '') {
            throw ExamValidationException::missingIdempotencyKey();
        }
        $this->authority->assertCan(ExamAdministrationAction::ManageTypes, $command->actorUserId, $command->schoolId);
        $weight = ExamTypeRules::weight($command->weightPercentage);
        $reason = trim($command->reason);
        if ($reason === '') {
            throw ExamTypeException::reasonRequired();
        }
        $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
        if ($cached !== null) {
            return new SaveExamTypeWeightVersionResult((int) $cached['version_no'], true);
        }

        $versionNo = $this->unitOfWork->transaction(function () use ($command, $weight, $reason): int {
            $this->types->lockForSchool($command->examTypeId, $command->schoolId) ?? throw ExamTypeException::notFound();
            if (! $this->versions->yearExists($command->academicYearId)) {
                throw ExamTypeException::yearInvalid();
            }
            $versionNo = $this->versions->nextVersion($command->schoolId, $command->examTypeId, $command->academicYearId);
            $this->versions->insert($command->schoolId, $command->examTypeId, $command->academicYearId, $versionNo, $weight, $reason, $command->actorUserId);
            $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, ['version_no' => $versionNo]);

            return $versionNo;
        });

        return new SaveExamTypeWeightVersionResult($versionNo);
    }
}
