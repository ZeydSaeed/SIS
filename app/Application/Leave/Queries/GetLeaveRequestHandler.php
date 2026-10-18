<?php

namespace App\Application\Leave\Queries;

use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Application\Leave\Contracts\LeaveAttachmentStorageInterface;
use App\Application\Leave\Contracts\LeaveReadRepositoryInterface;
use App\Application\Leave\Support\LeavePrivacy;
use App\Domain\Documents\Repositories\DocumentRepositoryInterface;
use App\Domain\Leave\Repositories\LeaveBalanceRepositoryInterface;
use App\Domain\Leave\Repositories\LeaveTypeRepositoryInterface;
use App\Domain\Leave\ValueObjects\RequesterKind;

/**
 * One request with its history, attachments (file names only — never the content), the policy version it is under
 * and the person's balance. Null when the viewer may not see it (another school, or not theirs) — the same answer
 * as a missing request.
 */
final class GetLeaveRequestHandler implements QueryHandler
{
    public function __construct(
        private readonly LeaveReadRepositoryInterface $read,
        private readonly LeaveTypeRepositoryInterface $types,
        private readonly LeaveBalanceRepositoryInterface $balances,
        private readonly DocumentRepositoryInterface $documents,
    ) {}

    /** @return array<string, mixed>|null */
    public function handle(Query $query): ?array
    {
        assert($query instanceof GetLeaveRequestQuery);
        $row = $this->read->request($query->schoolId, $query->requestId);
        $viewer = $query->viewer;
        $own = $row !== null && ((int) $row['requester_user_id'] === $viewer->userId || (int) $row['created_by'] === $viewer->userId);
        if ($row === null || (! $viewer->seesAll && ! $own)) {
            return null;
        }
        $masked = LeavePrivacy::hides($row, $viewer);
        $kind = RequesterKind::from((int) $row['requester_kind']);
        $balance = ($viewer->seesBalances || $own)
            ? $this->balances->balance($query->schoolId, (int) $row['academic_year_id'], (int) $row['leave_type_id'], $kind, (int) $row['person_id'])->toArray()
            : null;

        return LeavePrivacy::mask($row, $viewer) + [
            'own' => $own,
            'policy' => $this->types->policy($query->schoolId, (int) $row['policy_id'])?->toArray(),
            'balance' => $balance,
            'events' => array_map(static fn (array $e): array => $masked ? ['note' => null] + $e : $e, $this->read->events($query->schoolId, $query->requestId)),
            'attachments' => $masked ? [] : array_map(static fn ($d): array => [
                'id' => $d->id,
                'file_name' => $d->fileName,
                'mime_type' => $d->mimeType,
                'file_size' => $d->fileSize,
                'created_at' => $d->createdAt,
            ], $this->documents->listByEntity($query->schoolId, LeaveAttachmentStorageInterface::ENTITY_TYPE, $query->requestId)),
        ];
    }
}
