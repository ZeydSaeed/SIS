<?php

namespace App\Application\Timetable\Queries;

use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Domain\Shared\Services\AbbreviationSuggester;
use App\Domain\Timetable\Repositories\TimetableEngineReadRepositoryInterface;
use App\Domain\Timetable\Repositories\TimetableWorkspaceReadRepositoryInterface;
use App\Domain\Timetable\Support\TimetableDisplaySettings;

/**
 * The display catalogue keyed by entity id. Each row carries `short` — the abbreviation the timetable prints:
 * the owner's abbreviation, else a suggestion (people: the given name; labels: initials / the code for rooms).
 * The stored value stays the owner's; the suggestion is never written.
 */
final class GetTimetableDisplayCatalogHandler implements QueryHandler
{
    public function __construct(
        private readonly TimetableWorkspaceReadRepositoryInterface $workspace,
        private readonly AbbreviationSuggester $abbreviations,
        private readonly TimetableEngineReadRepositoryInterface $engine,
    ) {}

    /** @return array<string, array<int|string, mixed>> kind → id → row, plus `settings` (the display settings) */
    public function handle(Query $query): array
    {
        assert($query instanceof GetTimetableDisplayCatalogQuery);
        $catalog = $this->workspace->displayCatalog($query->schoolId, $query->academicYearId);

        $out = [];
        foreach ($catalog as $kind => $rows) {
            $out[$kind] = [];
            foreach ($rows as $row) {
                $suggested = match ($kind) {
                    'teachers' => $this->abbreviations->suggest($row['name'], AbbreviationSuggester::PERSON),
                    'rooms' => $row['code'],
                    'sections', 'classes' => $row['name'],
                    default => $this->abbreviations->suggest($row['name']),
                };
                $out[$kind][$row['id']] = $row + ['suggested' => $suggested, 'short' => $row['abbreviation'] ?? $suggested ?? $row['name']];
            }
        }

        // «تنسيق الجدول»: the saved layout of the school-year, normalised (defaults when never saved).
        $out['settings'] = TimetableDisplaySettings::from($this->engine->display($query->schoolId, $query->academicYearId))->toArray();

        return $out;
    }
}
