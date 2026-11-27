<?php

namespace App\Application\Results\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\UnitOfWork;
use App\Application\Results\Contracts\ResultPublicationRepositoryInterface;
use App\Application\Results\Results\PublishYearResultsResult;
use App\Application\Results\Support\PublicationSnapshotBuilder;
use App\Domain\Results\Exceptions\ResultPublicationException;

/**
 * Publishing is separate from finalizing: it is refused unless EVERY active student of the scope has a complete
 * current official result of the kind (see PublicationSnapshotBuilder). The published snapshot (enrollment, version, value) and its fingerprint are
 * stored so the published state can be proved later; result rows are not touched.
 *
 * The snapshot is built and its official rows are locked (FOR SHARE) in the same transaction as the insert, so a
 * concurrent rebuild either commits first (the lock check then refuses the stale snapshot) or waits for the publication.
 */
final class PublishYearResultsHandler implements CommandHandler
{
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly PublicationSnapshotBuilder $snapshots,
        private readonly ResultPublicationRepositoryInterface $publications,
    ) {}

    public function handle(Command $command): PublishYearResultsResult
    {
        assert($command instanceof PublishYearResultsCommand);

        [$id, $participants] = $this->unitOfWork->transaction(function () use ($command): array {
            $snapshot = $this->snapshots->build($command);
            if (! $this->publications->lockSnapshot($command->schoolId, $command->academicYearId, $command->kind, $snapshot, $command->termId, $command->subjectId)) {
                throw ResultPublicationException::snapshotChanged();
            }
            if ($this->publications->activeOverlapping($command->schoolId, $command->academicYearId, $command->classId, $command->sectionId, $command->kind, $command->termId, $command->subjectId) !== []) {
                throw ResultPublicationException::alreadyPublished();
            }
            $fingerprint = hash('sha256', implode('|', array_map(static fn (array $s): string => $s['enrollment_id'].':'.$s['version'].':'.($s['value'] ?? 'null'), $snapshot)));

            return [
                $this->publications->insert($command->schoolId, $command->academicYearId, $command->classId, $command->sectionId, $command->kind, $command->termId, $command->subjectId, $snapshot, $fingerprint, $command->actorUserId, $command->correlationId),
                count($snapshot),
            ];
        });

        return new PublishYearResultsResult($id, $participants);
    }
}
