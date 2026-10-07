<?php

namespace App\Application\Timetable\Queries;

use App\Application\Timetable\DTOs\TimetableLessonsDTO;
use App\Domain\Timetable\Repositories\TimetableVersionRepositoryInterface;

/** A version shown on the grid read-only (history: «ما النسخة التي كانت منشورة؟»). */
final class GetTimetableVersionEntriesHandler
{
    public function __construct(
        private readonly TimetableVersionRepositoryInterface $versions,
    ) {}

    public function handle(GetTimetableVersionEntriesQuery $query): ?TimetableLessonsDTO
    {
        $version = $this->versions->find($query->schoolId, $query->versionId);
        if ($version === null) {
            return null;
        }

        return new TimetableLessonsDTO(
            lessons: array_map(static fn (array $e): array => ['id' => $e['source_schedule_id'] ?? -$e['id'], 'locked' => false, 'joined_to' => null] + $e,
                $this->versions->entries($query->schoolId, $query->versionId)),
            meta: ['version' => $version],
        );
    }
}
