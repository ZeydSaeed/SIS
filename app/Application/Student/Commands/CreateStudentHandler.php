<?php

namespace App\Application\Student\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Student\Results\CreateStudentResult;
use App\Application\Student\Support\StudentNameFormatter;
use App\Domain\Student\Data\CreateStudentData;
use App\Domain\Student\Events\StudentRegistered;
use App\Domain\Student\Exceptions\DuplicateNationalIdException;
use App\Domain\Student\Exceptions\StudentCodeAlreadyExistsException;
use App\Domain\Student\Repositories\StudentRepositoryInterface;

final class CreateStudentHandler implements CommandHandler
{
    private const COMMAND_NAME = 'CreateStudent';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly StudentRepositoryInterface $students,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): CreateStudentResult
    {
        assert($command instanceof CreateStudentCommand);

        if ($command->idempotencyKey !== null) {
            $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
            if ($cached !== null) {
                return CreateStudentResult::fromIdempotency(
                    (int) $cached['student_id'],
                    (string) $cached['student_code'],
                );
            }
        }

        $studentCode = $command->studentCode ?? $this->students->generateStudentCode();
        if ($this->students->existsByCode($studentCode)) {
            throw StudentCodeAlreadyExistsException::forCode($studentCode);
        }

        if ($command->nationalId !== null && $this->students->existsByNationalId($command->nationalId)) {
            throw DuplicateNationalIdException::forNationalId($command->nationalId);
        }

        $fullName = StudentNameFormatter::fullName(
            $command->firstName,
            $command->middleName,
            $command->lastName,
        );

        $result = $this->unitOfWork->transaction(function () use ($command, $studentCode, $fullName): array {
            $studentId = $this->students->saveNew(new CreateStudentData(
                studentCode: $studentCode,
                firstName: $command->firstName,
                middleName: $command->middleName,
                lastName: $command->lastName,
                fullName: $fullName,
                gender: $command->gender,
                birthDate: $command->birthDate,
                nationalId: $command->nationalId,
                birthPlace: $command->birthPlace,
                nationality: $command->nationality,
            ));

            $this->outbox->stage(new StudentRegistered(
                studentId: $studentId,
                studentCode: $studentCode,
                fullName: $fullName,
                occurredAt: new \DateTimeImmutable,
            ));

            return ['id' => $studentId, 'student_code' => $studentCode];
        });

        if ($command->idempotencyKey !== null) {
            $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, [
                'student_id' => $result['id'],
                'student_code' => $result['student_code'],
            ]);
        }

        return CreateStudentResult::success($result['id'], $result['student_code']);
    }
}
