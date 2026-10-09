<?php

namespace App\Application\Organization\Support;

use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Organization\Results\RoomCatalogueResult;
use App\Domain\Organization\Events\RoomChanged;
use App\Domain\Organization\Support\OrganizationIdempotencyGuard;

/**
 * The write envelope of «الغرف الدراسية» and the organization appearance commands: idempotency (a retried
 * request returns the first id), one transaction owned by the handler, the outbox event staged inside it.
 */
final class RoomCatalogueTransaction
{
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly IdempotencyStore $idempotency,
        private readonly OutboxRepository $outbox,
    ) {}

    /** The id stored for a key already applied, or null. */
    public function replayed(?string $idempotencyKey, string $command): ?int
    {
        $cached = $this->idempotency->find(OrganizationIdempotencyGuard::requireKey($idempotencyKey), $command);

        return $cached === null ? null : (int) $cached['id'];
    }

    /**
     * @template T of RoomCatalogueResult
     *
     * @param  class-string<T>  $resultClass
     * @param  callable(string $at): int  $work  the write; returns the affected id
     * @return T
     */
    public function run(string $resultClass, ?string $idempotencyKey, string $command, string $target, int $schoolId, string $change, callable $work): RoomCatalogueResult
    {
        $key = OrganizationIdempotencyGuard::requireKey($idempotencyKey);

        $id = $this->unitOfWork->transaction(function () use ($work, $key, $command, $target, $schoolId, $change): int {
            $id = $work((new \DateTimeImmutable)->format('Y-m-d H:i:s'));
            $this->outbox->stage(new RoomChanged($target, $id, $schoolId, $change, new \DateTimeImmutable));
            $this->idempotency->store($key, $command, ['id' => $id]);

            return $id;
        });

        return $resultClass::success($id);
    }
}
