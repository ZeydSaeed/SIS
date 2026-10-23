<?php

namespace App\Application\Results\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Results\Contracts\TermResultSheetReadRepositoryInterface;
use App\Application\Results\Results\ProcessTermResultsBatchResult;
use App\Application\Results\Support\TermResultsBatchTargetValidator;
use App\Domain\Results\Support\TermResultIdempotencyGuard;
use App\Domain\Shared\Exceptions\SisDomainException;

/**
 * Term-results sheet: each seated student goes through the single-student Calculate / FinalizeTermResult command in
 * its own transaction, with a per-student key derived from the request key (a repeated request replays). Finalize
 * never re-finalizes a student who already has a current official result.
 */
final class ProcessTermResultsBatchHandler implements CommandHandler
{
    public function __construct(
        private readonly TermResultSheetReadRepositoryInterface $sheet,
        private readonly TermResultsBatchTargetValidator $target,
        private readonly CalculateTermResultHandler $calculate,
        private readonly FinalizeTermResultHandler $finalize,
    ) {}

    public function handle(Command $command): ProcessTermResultsBatchResult
    {
        assert($command instanceof ProcessTermResultsBatchCommand);

        $key = TermResultIdempotencyGuard::requireKey($command->idempotencyKey);
        $this->target->assertValid($command);

        $outcomes = [];
        foreach ($this->sheet->rows($command->schoolId, $command->academicYearId, $command->termId, $command->subjectId, $command->classId, $command->sectionId) as $row) {
            $outcomes[] = $command->mode === ProcessTermResultsBatchCommand::FINALIZE && $row['official'] !== null
                ? ['enrollment_id' => $row['enrollment_id'], 'status' => 'already_official']
                : ['enrollment_id' => $row['enrollment_id'], 'status' => $this->processOne($command, $row['enrollment_id'], $key)];
        }

        return ProcessTermResultsBatchResult::fromOutcomes($outcomes);
    }

    private function processOne(ProcessTermResultsBatchCommand $command, int $enrollmentId, string $key): string
    {
        $finalize = $command->mode === ProcessTermResultsBatchCommand::FINALIZE;
        $rowKey = 'term-batch:'.hash('sha256', $command->mode.'|'.$key.'|'.$enrollmentId);
        $args = [$command->schoolId, $enrollmentId, $command->academicYearId, $command->termId, $command->subjectId, $rowKey, $command->actorUserId];

        try {
            $result = $finalize
                ? $this->finalize->handle(new FinalizeTermResultCommand(...$args))
                : $this->calculate->handle(new CalculateTermResultCommand(...$args));
        } catch (SisDomainException $e) {
            return $e->errorCode();
        }

        return $result->fromIdempotencyCache ? 'replayed' : ($finalize ? 'finalized' : 'calculated');
    }
}
