<?php

namespace App\Application\Leave\Support;

use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Leave\Results\LeaveResult;
use App\Domain\Leave\Events\LeaveChanged;
use App\Domain\Leave\Exceptions\LeaveRuleViolation;

/**
 * The write envelope of every leave command: a required idempotency key (a retried request returns the first
 * outcome and writes nothing), one transaction owned by the handler, the outbox event staged inside it.
 */
final class LeaveTransaction
{
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly IdempotencyStore $idempotency,
        private readonly OutboxRepository $outbox,
    ) {}

    /**
     * @template T of LeaveResult
     *
     * @param  class-string<T>  $resultClass
     * @param  callable(): array{data: array<string, mixed>, event: LeaveChanged|null}  $work
     * @return T
     */
    public function run(string $resultClass, ?string $idempotencyKey, string $command, callable $work): LeaveResult
    {
        $key = trim((string) $idempotencyKey);
        if ($key === '') {
            throw LeaveRuleViolation::of('idempotency_key_required');
        }
        $cached = $this->idempotency->find($key, $command);
        if ($cached !== null) {
            return $resultClass::fromIdempotency($cached);
        }

        $data = $this->unitOfWork->transaction(function () use ($work, $key, $command): array {
            $outcome = $work();
            if ($outcome['event'] !== null) {
                $this->outbox->stage($outcome['event']);
            }
            $this->idempotency->store($key, $command, $outcome['data']);

            return $outcome['data'];
        });

        return $resultClass::success($data);
    }

    /**
     * @param  array<string, scalar|null>  $details
     */
    public static function event(string $target, int $id, int $schoolId, string $change, ?int $actor, array $details = []): LeaveChanged
    {
        return new LeaveChanged($target, $id, $schoolId, $change, $actor, $details, new \DateTimeImmutable);
    }
}
