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

        $snapshot = $this->snapshots->build($command);
        $fingerprint = hash('sha256', implode('|', array_map(static fn (array $s): string => $s['enrollment_id'].':'.$s['version'].':'.($s['value'] ?? 'null'), $snapshot)));

        $id = $this->unitOfWork->transaction(function () use ($command, $snapshot, $fingerprint): int {
            if ($this->publications->activeOverlapping($command->schoolId, $command->academicYearId, $command->classId, $command->sectionId, $command->kind, $command->termId, $command->subjectId) !== []) {
                throw ResultPublicationException::alreadyPublished();
            }

            return $this->publications->insert($command->schoolId, $command->academicYearId, $command->classId, $command->sectionId, $command->kind, $command->termId, $command->subjectId, $snapshot, $fingerprint, $command->actorUserId, $command->correlationId);
        });

        return new PublishYearResultsResult($id, count($snapshot));
    }
}
