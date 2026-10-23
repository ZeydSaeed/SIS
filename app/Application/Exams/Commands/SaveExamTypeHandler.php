<?php

namespace App\Application\Exams\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\UnitOfWork;
use App\Application\Exams\Contracts\ExamAdministrationAuthorityPort;
use App\Application\Exams\Results\SaveExamTypeResult;
use App\Application\Exams\Support\SaveExamTypeMutationService;
use App\Domain\Exams\Exceptions\ExamValidationException;
use App\Domain\Exams\Services\ExamTypeRules;
use App\Domain\Exams\Support\ExamIdempotencyGuard;
use App\Domain\Exams\ValueObjects\ExamAdministrationAction;

final class SaveExamTypeHandler implements CommandHandler
{
    public const COMMAND_NAME = 'Exams.SaveExamType';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly ExamAdministrationAuthorityPort $authority,
        private readonly SaveExamTypeMutationService $mutation,
    ) {}

    public function handle(Command $command): SaveExamTypeResult
    {
        assert($command instanceof SaveExamTypeCommand);

        if ($command->idempotencyKey === '') {
            throw ExamValidationException::missingIdempotencyKey();
        }

        $this->authority->assertCan(ExamAdministrationAction::ManageTypes, $command->actorUserId, $command->schoolId);

        $code = ExamTypeRules::code($command->code);
        $name = ExamTypeRules::name($command->name);
        $weight = ExamTypeRules::weight($command->weightPercentage);

        $fingerprint = ExamIdempotencyGuard::fingerprint(self::COMMAND_NAME, $command->schoolId, [
            'schema_version' => 1,
            'exam_type_id' => $command->examTypeId ?? '__new__',
            'code' => $code,
            'name' => $name,
            'weight_percentage' => $weight,
        ]);

        $payload = $this->unitOfWork->transaction(
            fn (): array => $this->mutation->execute($command, $code, $name, $weight, $fingerprint),
        );

        return $payload['_replay']
            ? SaveExamTypeResult::fromIdempotency($payload['exam_type_id'], $payload['created'])
            : SaveExamTypeResult::success($payload['exam_type_id'], $payload['created']);
    }
}
