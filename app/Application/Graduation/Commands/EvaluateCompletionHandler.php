<?php

namespace App\Application\Graduation\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Graduation\Contracts\GraduationAuthorityPort;
use App\Application\Graduation\Results\EvaluateCompletionResult;
use App\Domain\Graduation\Events\CompletionEvaluated;
use App\Domain\Graduation\Exceptions\GraduationBusinessConflictException;
use App\Domain\Graduation\Repositories\GraduationWriteRepositoryInterface;
use App\Domain\Graduation\Support\GraduationIdempotencyGuard;
use App\Domain\Graduation\ValueObjects\GraduationAction;

/**
 * Policy-driven evaluation: caller supplies requirement results against a pinned policy version.
 * Does not invent thresholds/GPA/course lists (HD-20/21 content remains institutional data).
 */
final class EvaluateCompletionHandler implements CommandHandler
{
    public const COMMAND_NAME = 'Graduation.EvaluateCompletion';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly GraduationWriteRepositoryInterface $graduation,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
        private readonly GraduationAuthorityPort $authority,
    ) {}

    public function handle(Command $command): EvaluateCompletionResult
    {
        assert($command instanceof EvaluateCompletionCommand);

        $this->authority->assertCan(
            GraduationAction::EvaluateCompletion,
            $command->actorUserId,
            $command->schoolId,
        );

        if ($command->requirementResults === []) {
            throw GraduationBusinessConflictException::invalidState(
                'EvaluateCompletion requires requirement results; inventing empty-pass is forbidden (DL-020).',
            );
        }

        $canonicalResults = $command->requirementResults;
        usort($canonicalResults, static fn (array $a, array $b): int => $a['requirement_definition_version_id'] <=> $b['requirement_definition_version_id']);

        $fingerprint = GraduationIdempotencyGuard::fingerprint(self::COMMAND_NAME, $command->schoolId, [
            'schema_version' => 1,
            'enrollment_id' => $command->enrollmentId,
            'eligibility_policy_version_id' => $command->eligibilityPolicyVersionId,
            'calculation_version' => $command->calculationVersion,
            'requirement_results' => $canonicalResults,
        ]);

        $payload = $this->unitOfWork->transaction(function () use ($command, $fingerprint): array {
            $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
            if ($cached !== null) {
                GraduationIdempotencyGuard::assertFingerprintMatch($cached, $fingerprint);

                return [
                    '_replay' => true,
                    'completion_outcome_id' => (int) $cached['completion_outcome_id'],
                    'completion_outcome_version_id' => (int) $cached['completion_outcome_version_id'],
                    'eligibility_status' => (int) $cached['eligibility_status'],
                ];
            }

            $outcomeId = $this->graduation->findCompletionOutcomeId($command->schoolId, $command->enrollmentId);
            if ($outcomeId === null) {
                throw GraduationBusinessConflictException::invalidState('Completion outcome missing for enrollment.');
            }

            $eligibilityStatus = $this->deriveEligibility($command->requirementResults);

            $versionId = $this->graduation->insertEvaluationVersion(
                $command->schoolId,
                $outcomeId,
                $command->eligibilityPolicyVersionId,
                $command->calculationVersion,
                $eligibilityStatus,
                2,
                $command->actorUserId,
                $command->requirementResults,
                $command->correlationId,
            );

            $this->outbox->stage(new CompletionEvaluated(
                completionOutcomeId: $outcomeId,
                completionOutcomeVersionId: $versionId,
                schoolId: $command->schoolId,
                enrollmentId: $command->enrollmentId,
                eligibilityStatus: $eligibilityStatus,
                evaluatedBy: $command->actorUserId,
                occurredAt: new \DateTimeImmutable,
            ), $command->correlationId);

            $resultPayload = GraduationIdempotencyGuard::withFingerprint([
                'completion_outcome_id' => $outcomeId,
                'completion_outcome_version_id' => $versionId,
                'eligibility_status' => $eligibilityStatus,
            ], $fingerprint, $command->schoolId);

            $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, $resultPayload);

            return $resultPayload + ['_replay' => false];
        });

        if ($payload['_replay'] === true) {
            return EvaluateCompletionResult::fromIdempotency(
                (int) $payload['completion_outcome_id'],
                (int) $payload['completion_outcome_version_id'],
                (int) $payload['eligibility_status'],
            );
        }

        return EvaluateCompletionResult::success(
            (int) $payload['completion_outcome_id'],
            (int) $payload['completion_outcome_version_id'],
            (int) $payload['eligibility_status'],
        );
    }

    /**
     * @param  list<array{requirement_definition_version_id:int,result_status:int}>  $results
     */
    private function deriveEligibility(array $results): int
    {
        foreach ($results as $row) {
            $status = (int) $row['result_status'];
            // 1 = satisfied only; missing evidence (3) and not satisfied (2) block eligibility
            if ($status !== 1) {
                return 3;
            }
        }

        return 2;
    }
}
