<?php

namespace App\Application\Imports\Queries;

use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Application\Imports\Contracts\SpreadsheetPort;
use App\Application\Imports\Support\ImportProfileRegistry;
use App\Domain\Imports\Repositories\ImportBatchRepositoryInterface as Batches;

/**
 * Builds a downloadable .xlsx: the template (header + one example row) or the error report (the original cells of
 * every refused row + the reasons as codes the page translates) — fix and re-upload it as is.
 */
final class BuildImportSheetHandler implements QueryHandler
{
    public function __construct(
        private readonly Batches $batches,
        private readonly ImportProfileRegistry $profiles,
        private readonly SpreadsheetPort $spreadsheets,
    ) {}

    /** @return array{file_name: string, contents: string}|null */
    public function handle(Query $query): ?array
    {
        assert($query instanceof BuildImportSheetQuery);
        $profile = $this->profiles->get($query->kind);
        if ($profile === null) {
            return null;
        }
        $columns = $profile->columns();
        $header = array_map(static fn (array $c): string => $c['label'].($c['required'] ? ' *' : ''), $columns);
        if ($query->batchId === null) {
            return [
                'file_name' => 'import-template-'.$query->kind.'.xlsx',
                'contents' => $this->spreadsheets->write($query->kind, [$header, array_map(static fn (array $c): string => $c['example'], $columns)]),
            ];
        }
        $batch = $this->batches->find($query->schoolId, $query->batchId);
        if ($batch === null || $batch['kind'] !== $query->kind) {
            return null;
        }
        $rows = [[...$header, 'row', 'errors']];
        foreach ($this->batches->rowsWithStatus($query->schoolId, $query->batchId, [Batches::ROW_ERROR, Batches::ROW_DUPLICATE, Batches::ROW_FAILED]) as $row) {
            $source = (array) ($row['data']['_source'] ?? []);
            $rows[] = [...array_map(static fn (array $c): string => (string) ($source[$c['key']] ?? ''), $columns), $row['row_number'], implode(' | ', $row['errors'])];
        }

        return ['file_name' => 'import-errors-'.$query->batchId.'.xlsx', 'contents' => $this->spreadsheets->write('errors', $rows)];
    }
}
