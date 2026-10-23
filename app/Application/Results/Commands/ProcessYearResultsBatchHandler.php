<?php

namespace App\Application\Results\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Results\Contracts\YearResultSheetReadRepositoryInterface;
use App\Application\Results\Results\ProcessYearResultsBatchResult;
use App\Application\Results\Support\TermResultsBatchTargetValidator;
use App\Domain\Results\Support\TermResultIdempotencyGuard;
use App\Domain\Shared\Exceptions\SisDomainException;

/**
 * Annual / GPA sheet: each student of the class goes through the single-student Calculate / Finalize AnnualResult or
 * Gpa command in its own transaction, with a per-student key derived from the request key (a repeated request
 * replays). Finalize never re-finalizes a student who already has a current official annual result / GPA.
 */
final class ProcessYearResultsBatchHandler implements CommandHandler
{
    public function __construct(
        private readonly YearResultSheetReadRepositoryInterface $sheet,
        private readonly TermResultsBatchTargetValidator $target,
        private readonly CalculateAnnualResultHandler $calculateAnnual,
        private readonly FinalizeAnnualResultHandler $finalizeAnnual,
        private readonly CalculateGpaHandler $calculateGpa,
        private readonly FinalizeGpaHandler $finalizeGpa,
    ) {}

    public function handle(Command $command): ProcessYearResultsBatchResult
    {
        assert($command instanceof ProcessYearResultsBatchCommand);

        $key = TermResultIdempotencyGuard::requireKey($command->idempotencyKey);
        $this->target->assertClassAndSection($command->schoolId, $command->academicYearId, $command->classId, $command->sectionId);
        $finalize = $command->mode === ProcessTermResultsBatchCommand::FINALIZE;

        $outcomes = [];
        foreach ($this->sheet->rows($command->schoolId, $command->academicYearId, $command->classId, $command->sectionId) as $row) {
            $outcomes[] = $finalize && $row[$command->target]['official'] !== null
                ? ['enrollment_id' => $row['enrollment_id'], 'status' => 'already_official']
                : ['enrollment_id' => $row['enrollment_id'], 'status' => $this->processOne($command, $row['enrollment_id'], $key, $finalize)];
        }

        return ProcessYearResultsBatchResult::fromOutcomes($outcomes);
    }

    private function processOne(ProcessYearResultsBatchCommand $command, int $enrollmentId, string $key, bool $finalize): string
    {
        $rowKey = 'year-batch:'.hash('sha256', $command->target.'|'.$command->mode.'|'.$key.'|'.$enrollmentId);
        $args = [$command->schoolId, $enrollmentId, $command->academicYearId, $rowKey, $command->actorUserId];

        try {
            $result = match ([$command->target, $finalize]) {
                [ProcessYearResultsBatchCommand::ANNUAL, false] => $this->calculateAnnual->handle(new CalculateAnnualResultCommand(...$args)),
                [ProcessYearResultsBatchCommand::ANNUAL, true] => $this->finalizeAnnual->handle(new FinalizeAnnualResultCommand(...$args)),
                [ProcessYearResultsBatchCommand::GPA, false] => $this->calculateGpa->handle(new CalculateGpaCommand(...$args)),
                [ProcessYearResultsBatchCommand::GPA, true] => $this->finalizeGpa->handle(new FinalizeGpaCommand(...$args)),
            };
        } catch (SisDomainException $e) {
            return $e->errorCode();
        }

        return $result->fromIdempotencyCache ? 'replayed' : ($finalize ? 'finalized' : 'calculated');
    }
}
