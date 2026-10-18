<?php

namespace App\Application\Academic\Support;

use App\Application\Academic\Results\HolidayResult;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Domain\Academic\Events\HolidayChanged;
use DomainException;

/**
 * The write envelope of «الاجازات»: idempotency (a retried request returns the first id), one transaction owned
 * by the handler, the outbox event staged inside it.
 */
final class HolidayTransaction
{
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly IdempotencyStore $idempotency,
        private readonly OutboxRepository $outbox,
    ) {}

    /** The id stored for a key already applied, or null. */
    public function replayed(?string $idempotencyKey, string $command): ?int
    {
        $cached = $this->idempotency->find($this->requireKey($idempotencyKey), $command);

        return $cached === null ? null : (int) $cached['id'];
    }

    /**
     * @template T of HolidayResult
     *
     * @param  class-string<T>  $resultClass
     * @param  callable(string $at): int  $work  the write; returns the affected id
     * @return T
     */
    public function run(string $resultClass, ?string $idempotencyKey, string $command, int $schoolId, string $change, callable $work): HolidayResult
    {
        $key = $this->requireKey($idempotencyKey);

        $id = $this->unitOfWork->transaction(function () use ($work, $key, $command, $schoolId, $change): int {
            $id = $work((new \DateTimeImmutable)->format('Y-m-d H:i:s'));
            $this->outbox->stage(new HolidayChanged($id, $schoolId, $change, new \DateTimeImmutable));
            $this->idempotency->store($key, $command, ['id' => $id]);

            return $id;
        });

        return $resultClass::success($id);
    }

    private function requireKey(?string $key): string
    {
        $trimmed = trim((string) $key);
        if ($trimmed === '') {
            throw new DomainException('Idempotency key is required for holiday commands.');
        }

        return $trimmed;
    }
}
