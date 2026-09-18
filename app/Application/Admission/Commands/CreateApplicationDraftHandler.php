<?php

namespace App\Application\Admission\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Admission\Results\CreateApplicationDraftResult;
use App\Domain\Admission\Data\CreateApplicationDraftData;
use App\Domain\Admission\Events\ApplicationDraftCreated;
use App\Domain\Admission\Repositories\AdmissionRepositoryInterface;
use App\Domain\Admission\Services\CreateApplicationDraftGuard;

final class CreateApplicationDraftHandler implements CommandHandler
{
    private const COMMAND_NAME = 'CreateApplicationDraft';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly AdmissionRepositoryInterface $admission,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
        private readonly CreateApplicationDraftGuard $guard,
    ) {}

    public function handle(Command $command): CreateApplicationDraftResult
    {
        assert($command instanceof CreateApplicationDraftCommand);

        if ($command->idempotencyKey !== null) {
            $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
            if ($cached !== null) {
                return CreateApplicationDraftResult::fromIdempotency(
                    (int) $cached['application_id'],
                    (string) $cached['application_number'],
                );
            }
        }

        $period = $this->guard->assertOpenPeriod(
            $command->applicationPeriodId,
            $command->schoolId,
            $command->gender,
        );

        $result = $this->unitOfWork->transaction(function () use ($command, $period): array {
            $number = $this->admission->generateApplicationNumber(
                $command->schoolId,
                $period['academic_year_id'],
            );

            $id = $this->admission->createApplication(new CreateApplicationDraftData(
                applicationPeriodId: $command->applicationPeriodId,
                applicationNumber: $number,
                firstName: $command->firstName,
                lastName: $command->lastName,
                birthDate: $command->birthDate,
                gender: $command->gender,
                gradeLevelId: $command->gradeLevelId,
                nationalId: $command->nationalId,
                specializationId: $command->specializationId,
                notes: $command->notes,
            ));

            $this->outbox->stage(new ApplicationDraftCreated(
                applicationId: $id,
                periodId: $command->applicationPeriodId,
                schoolId: $command->schoolId,
                applicationNumber: $number,
                occurredAt: new \DateTimeImmutable,
            ));

            return ['id' => $id, 'number' => $number];
        });

        if ($command->idempotencyKey !== null) {
            $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, [
                'application_id' => $result['id'],
                'application_number' => $result['number'],
            ]);
        }

        return CreateApplicationDraftResult::success($result['id'], $result['number']);
    }
}
