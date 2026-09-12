<?php

namespace App\Application\Results\Queries;

use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Application\Results\DTOs\IssuedTranscriptMetadataDTO;
use App\Domain\Results\Repositories\TranscriptRepositoryInterface;

final class GetIssuedTranscriptMetadataHandler implements QueryHandler
{
    public function __construct(
        private readonly TranscriptRepositoryInterface $transcripts,
    ) {}

    public function handle(Query $query): ?IssuedTranscriptMetadataDTO
    {
        assert($query instanceof GetIssuedTranscriptMetadataQuery);

        $row = $this->transcripts->findCurrentIssued(
            $query->schoolId,
            $query->enrollmentId,
            $query->academicYearId,
        );

        if ($row === null) {
            return null;
        }

        return new IssuedTranscriptMetadataDTO(
            schoolId: $query->schoolId,
            enrollmentId: $query->enrollmentId,
            studentId: $row->studentId,
            academicYearId: $query->academicYearId,
            transcriptId: $row->id,
            transcriptVersion: $row->transcriptVersion,
            transcriptNumber: $row->transcriptNumber,
            payloadHash: $row->payloadHash,
            storageKey: $row->storageKey,
            issuedAt: $row->issuedAt,
        );
    }
}
