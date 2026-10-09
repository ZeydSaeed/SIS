<?php

namespace App\Application\Imports\Support;

use App\Application\Documents\Contracts\DocumentObjectStoragePort;
use App\Application\Imports\Contracts\ImportProfile;
use App\Application\Imports\Contracts\SpreadsheetPort;
use App\Domain\Imports\Repositories\ImportBatchRepositoryInterface as Batches;

/**
 * Runs a batch (inside the queued job): parse → validate → preview, then — only after the user confirms — commit.
 *
 * Parse  maps the header row to the profile's columns (any accepted spelling), plans every row through the profile
 *        (nothing is written), and marks rows valid / error / duplicate (the same key twice in the file: the later
 *        rows are duplicates and are skipped).
 * Commit applies the valid rows in order through the profile (each row its own transaction in the owning
 *        handler); a refused row is marked failed with its error and the batch goes on. The report counts created,
 *        updated, skipped and failed rows.
 */
final class ImportEngine
{
    public function __construct(
        private readonly Batches $batches,
        private readonly ImportProfileRegistry $profiles,
        private readonly SpreadsheetPort $spreadsheets,
        private readonly DocumentObjectStoragePort $storage,
    ) {}

    public function parse(int $schoolId, int $batchId): void
    {
        $batch = $this->batches->find($schoolId, $batchId);
        if ($batch === null || $batch['status'] !== Batches::PARSING) {
            return;
        }
        $profile = $this->profiles->get($batch['kind']);
        $contents = $this->storage->get($batch['storage_key']);
        $rows = $contents === null ? null : $this->spreadsheets->read($contents, $batch['file_name']);
        if ($profile === null || $rows === null) {
            $this->batches->setStatus($schoolId, $batchId, Batches::FAILED, 'import.file_unreadable', self::now());

            return;
        }
        $header = array_shift($rows) ?? [];
        [$map, $missing] = self::mapHeader($profile, $header);
        if ($missing !== []) {
            $this->batches->setStatus($schoolId, $batchId, Batches::FAILED, 'import.missing_columns:'.implode('،', $missing), self::now());

            return;
        }
        if ($rows === []) {
            $this->batches->setStatus($schoolId, $batchId, Batches::FAILED, 'import.no_rows', self::now());

            return;
        }

        $context = new ImportContext($schoolId, $batch['academic_year_id'], $batch['created_by'], $batchId);
        $planned = [];
        $seen = [];
        foreach ($rows as $index => $cells) {
            if (implode('', $cells) === '') {
                continue;
            }
            $row = [];
            foreach ($map as $key => $column) {
                $row[$key] = trim((string) ($cells[$column] ?? ''));
            }
            $plan = $profile->plan($context, $row);
            $status = $plan['errors'] !== [] ? Batches::ROW_ERROR : Batches::ROW_VALID;
            if ($status === Batches::ROW_VALID && $plan['key'] !== null) {
                if (isset($seen[$plan['key']])) {
                    $status = Batches::ROW_DUPLICATE;
                    $plan['errors'] = ['import.duplicate_in_file:'.$seen[$plan['key']]];
                    $plan['action'] = ImportProfile::SKIP;
                } else {
                    $seen[$plan['key']] = $index + 2;
                }
            }
            $planned[] = [
                'row_number' => $index + 2,
                'data' => $plan['data'] + ['_source' => $row],
                'action' => $plan['action'],
                'status' => $status,
                'errors' => $plan['errors'],
                'entity_id' => $plan['entity_id'],
            ];
        }
        $this->batches->storeParsed($schoolId, $batchId, $planned, self::now());
    }

    public function commit(int $schoolId, int $batchId, ?int $userId): void
    {
        $batch = $this->batches->find($schoolId, $batchId);
        $profile = $batch === null ? null : $this->profiles->get($batch['kind']);
        if ($batch === null || $profile === null || $batch['status'] !== Batches::COMMITTING) {
            return;
        }
        $context = new ImportContext($schoolId, $batch['academic_year_id'], $userId, $batchId);
        $result = ['created' => 0, 'updated' => 0, 'skipped' => $batch['duplicate_rows'], 'failed' => 0, 'errors' => $batch['error_rows']];
        foreach ($this->batches->rowsWithStatus($schoolId, $batchId, [Batches::ROW_VALID]) as $row) {
            if ($row['action'] === ImportProfile::SKIP) {
                $result['skipped']++;
                $this->batches->markRow($schoolId, $row['id'], Batches::ROW_COMMITTED, $row['entity_id'], [], self::now());

                continue;
            }
            $data = $row['data'];
            unset($data['_source']);
            try {
                $outcome = $profile->commit($context, $data, $row['action'], $row['entity_id'], 'import-'.$batchId.'-'.$row['row_number']);
            } catch (\Throwable $e) {
                $outcome = ['ok' => false, 'entity_id' => null, 'error' => self::errorCode($e)];
            }
            if ($outcome['ok']) {
                $result[$row['action'] === ImportProfile::CREATE ? 'created' : 'updated']++;
                $this->batches->markRow($schoolId, $row['id'], Batches::ROW_COMMITTED, $outcome['entity_id'], [], self::now());
            } else {
                $result['failed']++;
                $this->batches->markRow($schoolId, $row['id'], Batches::ROW_FAILED, null, [(string) $outcome['error']], self::now());
            }
        }
        $this->batches->finishCommit($schoolId, $batchId, $result, $userId, self::now());
    }

    /**
     * @param  list<string>  $header
     * @return array{0: array<string, int>, 1: list<string>} column key → cell index, and the missing required labels
     */
    public static function mapHeader(ImportProfile $profile, array $header): array
    {
        $normalised = array_map(static fn (string $h): string => ImportValues::header($h), $header);
        $map = [];
        $missing = [];
        foreach ($profile->columns() as $column) {
            $names = array_map(static fn (string $n): string => ImportValues::header($n), [$column['key'], $column['label'], ...$column['aliases']]);
            $found = null;
            foreach ($normalised as $index => $name) {
                if ($name !== '' && in_array($name, $names, true)) {
                    $found = $index;
                    break;
                }
            }
            if ($found !== null) {
                $map[$column['key']] = $found;
            } elseif ($column['required']) {
                $missing[] = $column['label'];
            }
        }

        return [$map, $missing];
    }

    /** A domain refusal keeps its code; anything else is reported generically (details stay in the logs). */
    private static function errorCode(\Throwable $e): string
    {
        return method_exists($e, 'errorCode') ? (string) $e->errorCode() : 'import.row_failed';
    }

    private static function now(): string
    {
        return (new \DateTimeImmutable)->format('Y-m-d H:i:s');
    }
}
