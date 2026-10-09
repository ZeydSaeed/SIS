<?php

namespace App\Application\Imports\Queries;

use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Application\Imports\Support\ImportProfileRegistry;
use App\Domain\Imports\Repositories\ImportBatchRepositoryInterface;

final class GetImportCenterHandler implements QueryHandler
{
    public const ROWS_PER_PAGE = 50;

    public function __construct(
        private readonly ImportBatchRepositoryInterface $batches,
        private readonly ImportProfileRegistry $profiles,
    ) {}

    /** @return array{kinds: list<array<string, mixed>>, batches: list<array<string, mixed>>, batch: array<string, mixed>|null, rows: array{rows: list<array<string, mixed>>, total: int, page: int, per_page: int}|null} */
    public function handle(Query $query): array
    {
        assert($query instanceof GetImportCenterQuery);
        $kinds = [];
        foreach ($this->profiles->all() as $kind => $profile) {
            if (in_array($kind, $query->allowedKinds, true)) {
                $kinds[] = ['kind' => $kind, 'needs_year' => $profile->needsYear(), 'columns' => $profile->columns()];
            }
        }
        $strip = static fn (array $b): array => array_diff_key($b, ['storage_key' => true]);
        $batch = $query->batchId === null ? null : $this->batches->find($query->schoolId, $query->batchId);
        if ($batch !== null && ! in_array($batch['kind'], $query->allowedKinds, true)) {
            $batch = null;
        }
        $rows = null;
        if ($batch !== null) {
            $page = $this->batches->rows($query->schoolId, $batch['id'], $query->rowStatus, $query->page, self::ROWS_PER_PAGE);
            $rows = $page + ['page' => max(1, $query->page), 'per_page' => self::ROWS_PER_PAGE];
        }

        return [
            'kinds' => $kinds,
            'batches' => array_values(array_map($strip, array_filter(
                $this->batches->latest($query->schoolId, 30),
                static fn (array $b): bool => in_array($b['kind'], $query->allowedKinds, true),
            ))),
            'batch' => $batch === null ? null : $strip($batch),
            'rows' => $rows,
        ];
    }
}
