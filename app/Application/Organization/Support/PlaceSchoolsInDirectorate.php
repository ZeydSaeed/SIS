<?php

namespace App\Application\Organization\Support;

use App\Application\Contracts\OutboxRepository;
use App\Domain\Organization\Events\SchoolUpdated;
use App\Domain\Organization\Repositories\SchoolRepositoryInterface;

/**
 * Moves schools into a directorate (inside the caller's transaction).
 * Schools already there are left untouched.
 */
final class PlaceSchoolsInDirectorate
{
    public function __construct(
        private readonly SchoolRepositoryInterface $schools,
        private readonly OutboxRepository $outbox,
    ) {}

    /**
     * @param  list<int>  $schoolIds
     */
    public function place(int $directorateId, array $schoolIds, \DateTimeImmutable $now): void
    {
        foreach (array_values(array_unique($schoolIds)) as $schoolId) {
            $school = $this->schools->find($schoolId);
            if ($school === null || $school->directorateId === $directorateId) {
                continue;
            }

            $this->schools->update(
                $schoolId,
                ['directorate_id' => $directorateId],
                $now->format(\DateTimeInterface::ATOM),
            );
            $this->outbox->stage(new SchoolUpdated($schoolId, $now));
        }
    }
}
