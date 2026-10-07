<?php

namespace App\Application\Timetable\Queries;

use App\Application\Timetable\DTOs\TimetableEngineDTO;
use App\Application\Timetable\Support\TimetableBoardLoader;
use App\Domain\Timetable\Constraints\ConstraintRuleCatalogue;
use App\Domain\Timetable\Data\TimetableBoard;
use App\Domain\Timetable\Repositories\GenerationRunRepositoryInterface;
use App\Domain\Timetable\Repositories\TimetableVersionRepositoryInterface;
use App\Domain\Timetable\Services\EffectiveVersionSelector;
use App\Domain\Timetable\Services\TimetableFingerprint;

final class GetTimetableEngineHandler
{
    public function __construct(
        private readonly TimetableBoardLoader $boards,
        private readonly GenerationRunRepositoryInterface $runs,
        private readonly TimetableVersionRepositoryInterface $versions,
        private readonly TimetableFingerprint $fingerprints,
        private readonly EffectiveVersionSelector $selector,
    ) {}

    /** @param  TimetableBoard|null  $board  already loaded board (the workspace's) */
    public function handle(GetTimetableEngineQuery $query, ?TimetableBoard $board = null): TimetableEngineDTO
    {
        $board ??= $this->boards->load($query->schoolId, $query->academicYearId);
        $versions = $this->versions->listForYear($query->schoolId, $query->academicYearId);
        $published = $this->versions->currentPublished($query->schoolId, $query->academicYearId);
        $fingerprint = $this->fingerprints->of($board);

        return new TimetableEngineDTO(
            settings: $board->settings->toArray(),
            activities: $board->activities,
            groups: array_values($board->groups),
            availability: $board->availability,
            rules: $board->rules,
            catalogue: array_map(static fn (string $type): array => [
                'type' => $type,
                'kind' => ConstraintRuleCatalogue::kind($type),
                'scopes' => ConstraintRuleCatalogue::scopesOf($type),
                'params' => ConstraintRuleCatalogue::paramsOf($type),
            ], ConstraintRuleCatalogue::types()),
            rooms: array_values($board->rooms),
            workshops: array_values($board->workshops),
            runs: $this->runs->recent($query->schoolId, $query->academicYearId, 8),
            versions: array_map(static fn (array $v): array => $v + ['stale' => $v['source_fingerprint'] !== $fingerprint], $versions),
            status: [
                'published_version_id' => $published['id'] ?? null,
                'effective_version_id' => $this->selector->select($versions, (new \DateTimeImmutable)->format('Y-m-d')),
                'stale' => $published !== null && $published['source_fingerprint'] !== $fingerprint,
                'fingerprint' => $fingerprint,
            ],
        );
    }
}
