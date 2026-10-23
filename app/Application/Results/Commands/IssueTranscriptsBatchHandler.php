<?php

namespace App\Application\Results\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Results\Contracts\YearResultSheetReadRepositoryInterface;
use App\Application\Results\Results\IssueTranscriptsBatchResult;
use App\Application\Results\Support\TermResultsBatchTargetValidator;
use App\Domain\Results\Support\TermResultIdempotencyGuard;
use App\Domain\Shared\Exceptions\SisDomainException;

/**
 * Transcripts of a class: each student goes through the single-student IssueTranscript command in its own transaction,
 * with a per-student key derived from the request key (a repeated request replays). A student whose current transcript
 * was issued after the current official GPA is skipped — issuing it again would only add an identical version.
 */
final class IssueTranscriptsBatchHandler implements CommandHandler
{
    public function __construct(
        private readonly YearResultSheetReadRepositoryInterface $sheet,
        private readonly TermResultsBatchTargetValidator $target,
        private readonly IssueTranscriptHandler $issue,
    ) {}

    public function handle(Command $command): IssueTranscriptsBatchResult
    {
        assert($command instanceof IssueTranscriptsBatchCommand);

        $key = TermResultIdempotencyGuard::requireKey($command->idempotencyKey);
        $this->target->assertClassAndSection($command->schoolId, $command->academicYearId, $command->classId, $command->sectionId);

        $outcomes = [];
        foreach ($this->sheet->rows($command->schoolId, $command->academicYearId, $command->classId, $command->sectionId) as $row) {
            $outcomes[] = ($row['transcript']['up_to_date'] ?? false)
                ? ['enrollment_id' => $row['enrollment_id'], 'status' => 'already_issued']
                : ['enrollment_id' => $row['enrollment_id'], 'status' => $this->issueOne($command, $row['enrollment_id'], $key)];
        }

        return IssueTranscriptsBatchResult::fromOutcomes($outcomes);
    }

    private function issueOne(IssueTranscriptsBatchCommand $command, int $enrollmentId, string $key): string
    {
        try {
            $result = $this->issue->handle(new IssueTranscriptCommand(
                schoolId: $command->schoolId,
                enrollmentId: $enrollmentId,
                academicYearId: $command->academicYearId,
                idempotencyKey: 'transcript-batch:'.hash('sha256', $key.'|'.$enrollmentId),
                issuedBy: $command->actorUserId,
            ));
        } catch (SisDomainException $e) {
            return $e->errorCode();
        }

        return $result->fromIdempotencyCache ? 'replayed' : 'issued';
    }
}
