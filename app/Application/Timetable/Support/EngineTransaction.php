<?php

namespace App\Application\Timetable\Support;

use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Timetable\Results\TimetableEngineResult;
use App\Domain\Shared\Exceptions\SisDomainException;
use App\Domain\Timetable\Events\TimetableEngineChanged;

/**
 * The write envelope every engine command shares: idempotency (a retried request returns the first result),
 * one transaction owned by the handler, the outbox event staged inside it, and domain refusals turned into
 * result error codes.
 */
final class EngineTransaction
{
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly IdempotencyStore $idempotency,
        private readonly OutboxRepository $outbox,
    ) {}

    /**
     * @template T of TimetableEngineResult
     *
     * @param  class-string<T>  $resultClass
     * @param  callable(): T  $work  runs inside the transaction; a failed result rolls back
     * @return T
     */
    public function run(string $resultClass, ?string $idempotencyKey, string $command, callable $work): TimetableEngineResult
    {
        $key = trim((string) $idempotencyKey);
        if ($key !== '') {
            $cached = $this->idempotency->find($key, $command);
            if ($cached !== null) {
                return $resultClass::cached($cached);
            }
        }

        try {
            return $this->unitOfWork->transaction(function () use ($work, $key, $command): TimetableEngineResult {
                $result = $work();
                if ($result->failed()) {
                    throw new EngineRollback($result);
                }
                if ($key !== '') {
                    $this->idempotency->store($key, $command, $result->payload());
                }

                return $result;
            });
        } catch (EngineRollback $rollback) {
            /** @var T */
            return $rollback->result;
        } catch (SisDomainException $e) {
            return $resultClass::failure($e->errorCode());
        }
    }

    /** @param  array<string, mixed>  $details */
    public function stage(string $action, int $schoolId, int $academicYearId, ?int $subjectId, ?int $actorUserId, array $details = []): void
    {
        $this->outbox->stage(new TimetableEngineChanged($action, $schoolId, $academicYearId, $subjectId, $actorUserId, $details, new \DateTimeImmutable));
    }
}
