<?php

namespace App\Application\Results\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Results\Results\IssueTranscriptResult;
use App\Domain\Results\Data\PersistIssuedTranscriptData;
use App\Domain\Results\Events\TranscriptIssued;
use App\Domain\Results\Exceptions\TermResultEnrollmentNotFoundException;
use App\Domain\Results\Exceptions\TranscriptOfficialGpaMissingException;
use App\Domain\Results\Repositories\TranscriptRepositoryInterface;
use App\Domain\Results\Support\TermResultIdempotencyGuard;

final class IssueTranscriptHandler implements CommandHandler
{
    private const COMMAND_NAME = 'IssueTranscript';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly TranscriptRepositoryInterface $transcripts,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): IssueTranscriptResult
    {
        assert($command instanceof IssueTranscriptCommand);

        $idempotencyKey = TermResultIdempotencyGuard::requireKey($command->idempotencyKey);

        $cached = $this->idempotency->find($idempotencyKey, self::COMMAND_NAME);
        if ($cached !== null) {
            return IssueTranscriptResult::fromIdempotency(
                (int) $cached['transcript_id'],
                (int) $cached['transcript_version'],
                (string) $cached['transcript_number'],
                (string) $cached['payload_hash'],
            );
        }

        $enrollment = $this->transcripts->findEnrollmentIdentity(
            $command->enrollmentId,
            $command->schoolId,
            $command->academicYearId,
        );
        if ($enrollment === null) {
            throw TermResultEnrollmentNotFoundException::forId($command->enrollmentId);
        }

        $source = $this->transcripts->findOfficialYearGpaSource(
            $command->schoolId,
            $command->enrollmentId,
            $command->academicYearId,
        );
        if ($source === null) {
            throw TranscriptOfficialGpaMissingException::forIdentity();
        }

        $sourceFingerprint = hash('sha256', implode('|', [
            $source->gpaResultId,
            $source->gpaFingerprint,
            $source->annualResultId ?? 'null',
            $source->annualFingerprint ?? 'null',
            'transcript-v1',
        ]));
        $payloadHash = hash('sha256', implode('|', [
            $command->schoolId,
            $command->enrollmentId,
            $command->academicYearId,
            $sourceFingerprint,
            $source->gpaValue ?? 'null',
            $source->scaleCode,
            $command->storageKey ?? '',
        ]));

        $version = $this->transcripts->nextTranscriptVersion(
            $command->schoolId,
            $command->enrollmentId,
            $command->academicYearId,
        );
        $transcriptNumber = sprintf(
            'TR-%d-%d-%d-v%d',
            $command->schoolId,
            $command->enrollmentId,
            $command->academicYearId,
            $version,
        );
        $at = now()->toIso8601String();

        $transcriptId = $this->unitOfWork->transaction(function () use (
            $command,
            $enrollment,
            $source,
            $sourceFingerprint,
            $payloadHash,
            $version,
            $transcriptNumber,
            $at,
            $idempotencyKey,
        ): int {
            $id = $this->transcripts->insertIssued(new PersistIssuedTranscriptData(
                schoolId: $command->schoolId,
                studentId: $enrollment['student_id'],
                enrollmentId: $command->enrollmentId,
                academicYearId: $command->academicYearId,
                transcriptVersion: $version,
                transcriptNumber: $transcriptNumber,
                storageKey: $command->storageKey,
                payloadHash: $payloadHash,
                sourceFingerprint: $sourceFingerprint,
                policyPin: [
                    'packaging' => 'metadata_only',
                    'pdf' => 'deferred',
                    'scale' => $source->scaleCode,
                    'gpa_result_id' => $source->gpaResultId,
                ],
                issuedAt: $at,
                issuedBy: $command->issuedBy,
                correlationId: $command->correlationId,
            ));

            $this->outbox->stage(new TranscriptIssued(
                transcriptId: $id,
                schoolId: $command->schoolId,
                enrollmentId: $command->enrollmentId,
                studentId: $enrollment['student_id'],
                academicYearId: $command->academicYearId,
                transcriptVersion: $version,
                transcriptNumber: $transcriptNumber,
                payloadHash: $payloadHash,
                occurredAt: new \DateTimeImmutable,
            ));

            $this->idempotency->store($idempotencyKey, self::COMMAND_NAME, [
                'transcript_id' => $id,
                'transcript_version' => $version,
                'transcript_number' => $transcriptNumber,
                'payload_hash' => $payloadHash,
            ]);

            return $id;
        });

        return IssueTranscriptResult::success($transcriptId, $version, $transcriptNumber, $payloadHash);
    }
}
