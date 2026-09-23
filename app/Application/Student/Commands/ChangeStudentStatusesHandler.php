<?php

namespace App\Application\Student\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Student\Results\ChangeStudentStatusesResult;
use App\Application\Enrollment\Services\ApplyStudentEnrollmentStatusSync;
use App\Domain\Shared\Exceptions\SisDomainException;
use App\Domain\Student\Events\StudentStatusChanged;
use App\Domain\Student\Exceptions\StudentNotFoundException;
use App\Domain\Student\Repositories\StudentRepositoryInterface;
use App\Domain\Student\ValueObjects\StudentStatus;

final class ChangeStudentStatusesHandler implements CommandHandler
{
    private const COMMAND_NAME = 'ChangeStudentStatuses';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly StudentRepositoryInterface $students,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
        private readonly ApplyStudentEnrollmentStatusSync $statusSync,
    ) {}

    public function handle(Command $command): ChangeStudentStatusesResult
    {
        assert($command instanceof ChangeStudentStatusesCommand);

        $studentIds = array_values(array_unique(array_filter(
            $command->studentIds,
            static fn (int $id): bool => $id > 0,
        )));

        if ($studentIds === []) {
            throw SisDomainException::withCode('student.bulk_status_empty');
        }

        $status = StudentStatus::tryFrom($command->status);
        if ($status === null) {
            throw SisDomainException::withCode('student.invalid_status');
        }

        if ($command->idempotencyKey !== null) {
            $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
            if ($cached !== null) {
                /** @var list<int> $cachedIds */
                $cachedIds = array_map('intval', $cached['student_ids'] ?? []);

                return ChangeStudentStatusesResult::fromIdempotency(
                    $cachedIds,
                    (int) $cached['status'],
                );
            }
        }

        /** @var list<array{id:int, from:int}> $prepared */
        $prepared = [];
        foreach ($studentIds as $studentId) {
            $student = $this->students->findByIdForSchool($studentId, $command->schoolId);
            if ($student === null) {
                throw StudentNotFoundException::forId($studentId);
            }

            $from = $student->status();
            $student->changeStatus($status);
            $prepared[] = ['id' => $studentId, 'from' => $from->value];
        }

        $this->unitOfWork->transaction(function () use ($command, $status, $prepared): void {
            foreach ($prepared as $item) {
                $this->students->updateStatus($item['id'], $status->value);
                $this->outbox->stage(new StudentStatusChanged(
                    studentId: $item['id'],
                    schoolId: $command->schoolId,
                    fromStatus: $item['from'],
                    toStatus: $status->value,
                    occurredAt: new \DateTimeImmutable,
                ));
            }

            $this->statusSync->syncEnrollmentsFromStudent(
                schoolId: $command->schoolId,
                studentIds: array_map(static fn (array $item): int => $item['id'], $prepared),
                studentStatus: $status,
                effectiveTo: (new \DateTimeImmutable)->format('Y-m-d'),
            );
        });

        if ($command->idempotencyKey !== null) {
            $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, [
                'student_ids' => $studentIds,
                'status' => $status->value,
            ]);
        }

        return ChangeStudentStatusesResult::success($studentIds, $status->value);
    }
}
