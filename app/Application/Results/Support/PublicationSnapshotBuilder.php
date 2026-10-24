<?php

namespace App\Application\Results\Support;

use App\Application\Results\Commands\PublishYearResultsCommand;
use App\Application\Results\Contracts\TermResultSheetReadRepositoryInterface;
use App\Application\Results\Contracts\YearResultSheetReadRepositoryInterface;
use App\Domain\Results\Exceptions\ResultPublicationException;

/**
 * What a publication would publish: for every active student of the scope the current official, complete result of
 * the kind (annual / GPA / term of a subject) or the up-to-date issued transcript. A student without one makes the
 * whole scope not ready — publication is all or nothing.
 */
final class PublicationSnapshotBuilder
{
    public const KINDS = ['annual', 'gpa', 'term', 'transcript'];

    public function __construct(
        private readonly YearResultSheetReadRepositoryInterface $year,
        private readonly TermResultSheetReadRepositoryInterface $term,
        private readonly TermResultsBatchTargetValidator $target,
    ) {}

    /** @return list<array{enrollment_id: int, version: int, value: ?string}> ordered by enrollment */
    public function build(PublishYearResultsCommand $command): array
    {
        if (! in_array($command->kind, self::KINDS, true)) {
            throw ResultPublicationException::kindInvalid($command->kind);
        }
        $this->target->assertClassAndSection($command->schoolId, $command->academicYearId, $command->classId, $command->sectionId);

        $entries = $command->kind === 'term' ? $this->termEntries($command) : $this->yearEntries($command);
        $total = count($entries);
        $ready = array_values(array_filter($entries, static fn (?array $e): bool => $e !== null));
        if ($total === 0 || count($ready) !== $total) {
            throw ResultPublicationException::notReady($total - count($ready), $total);
        }
        usort($ready, static fn (array $a, array $b): int => $a['enrollment_id'] <=> $b['enrollment_id']);

        return $ready;
    }

    /** @return list<array{enrollment_id: int, version: int, value: ?string}|null> */
    private function termEntries(PublishYearResultsCommand $command): array
    {
        if ($command->termId === null || $command->subjectId === null || ! $this->term->termInYear($command->termId, $command->academicYearId)) {
            throw ResultPublicationException::scopeInvalid();
        }
        $entries = [];
        foreach ($this->term->rows($command->schoolId, $command->academicYearId, $command->termId, $command->subjectId, $command->classId, $command->sectionId) as $row) {
            $official = $row['official'];
            $entries[] = $official !== null && ! $official['incomplete']
                ? ['enrollment_id' => $row['enrollment_id'], 'version' => $official['version'], 'value' => $official['weighted_total']]
                : null;
        }

        return $entries;
    }

    /** @return list<array{enrollment_id: int, version: int, value: ?string}|null> */
    private function yearEntries(PublishYearResultsCommand $command): array
    {
        $entries = [];
        foreach ($this->year->rows($command->schoolId, $command->academicYearId, $command->classId, $command->sectionId) as $row) {
            if ($command->kind === 'transcript') {
                $t = $row['transcript'];
                $entries[] = $t !== null && $t['up_to_date'] ? ['enrollment_id' => $row['enrollment_id'], 'version' => $t['version'], 'value' => $t['number']] : null;

                continue;
            }
            $official = $row[$command->kind]['official'];
            $entries[] = $official !== null && ! $official['incomplete']
                ? ['enrollment_id' => $row['enrollment_id'], 'version' => $official['version'], 'value' => $official['value']]
                : null;
        }

        return $entries;
    }
}
