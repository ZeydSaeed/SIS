<?php

namespace App\Console\Commands;

use App\Application\Results\Contracts\ResultPublicationRepositoryInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * READ-ONLY report of published results whose official result changed after publishing (EXAMIS operations T-3). It never
 * modifies anything. Exit code 1 and one `results.publication.drift` error log line per drifted publication when there is
 * drift, so a scheduler / log-based alert can act on it; 0 when there is none.
 *
 *   php artisan sis:report-publication-drift [--school=ID] [--json]
 */
final class ReportPublicationDriftCommand extends Command
{
    protected $signature = 'sis:report-publication-drift {--school= : Only this school id} {--json : Print JSON instead of a table}';

    protected $description = 'Read-only: list active result publications whose official result changed since publishing, by school, year and kind';

    public function handle(ResultPublicationRepositoryInterface $publications): int
    {
        $schools = DB::table('organization.schools')
            ->when($this->option('school') !== null, fn ($q) => $q->where('id', (int) $this->option('school')))
            ->orderBy('id')->pluck('name', 'id');

        $report = [];
        foreach ($schools as $schoolId => $name) {
            // RLS keys on this setting: the report sees exactly one school at a time, like the application does.
            DB::statement("SELECT set_config('app.current_school_id', ?, false)", [(string) $schoolId]);
            foreach ($publications->activeWithDrift((int) $schoolId) as $row) {
                $report[] = ['school_id' => (int) $schoolId, 'school' => (string) $name] + $row;
            }
        }
        DB::statement("SELECT set_config('app.current_school_id', '', false)");

        $drifted = array_values(array_filter($report, static fn (array $r): bool => $r['drifted'] > 0));
        foreach ($drifted as $row) {
            Log::error('results.publication.drift', $row);
        }

        if ($this->option('json')) {
            $this->line((string) json_encode(['active' => count($report), 'drifted' => $drifted], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        } else {
            $this->table(['school', 'year', 'kind', 'class', 'section', 'published', 'participants', 'drifted'], array_map(static fn (array $r): array => [
                $r['school_id'], $r['academic_year_id'], $r['kind'], $r['class_id'], $r['section_id'] ?? '—', substr($r['published_at'], 0, 16), $r['participants'], $r['drifted'],
            ], $report));
            $this->info(count($report).' active publication(s), '.count($drifted).' with drift.');
        }

        return $drifted === [] ? self::SUCCESS : self::FAILURE;
    }
}
