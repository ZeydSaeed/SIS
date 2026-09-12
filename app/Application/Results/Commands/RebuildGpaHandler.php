<?php

namespace App\Application\Results\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Results\Results\RebuildGpaResult;
use App\Application\Results\Support\GpaRebuildService;
use App\Domain\Results\Exceptions\TermResultEnrollmentNotFoundException;
use App\Domain\Results\Repositories\GpaResultRepositoryInterface;
use App\Domain\Results\Support\TermResultIdempotencyGuard;

final class RebuildGpaHandler implements CommandHandler
{
    private const COMMAND_NAME = 'RebuildGpa';

    public function __construct(
        private readonly GpaResultRepositoryInterface $gpaResults,
        private readonly GpaRebuildService $rebuild,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): RebuildGpaResult
    {
        assert($command instanceof RebuildGpaCommand);

        $idempotencyKey = TermResultIdempotencyGuard::requireKey($command->idempotencyKey);
        $mode = $this->rebuild->resolveMode($command->mode);

        $cached = $this->idempotency->find($idempotencyKey, self::COMMAND_NAME);
        if ($cached !== null) {
            return RebuildGpaResult::fromIdempotency(
                (int) $cached['gpa_result_id'],
                (int) $cached['result_version'],
                isset($cached['gpa_value']) ? (string) $cached['gpa_value'] : null,
                (bool) ($cached['incomplete'] ?? false),
                (bool) ($cached['unchanged'] ?? false),
                (string) $cached['mode'],
            );
        }

        $enrollment = $this->gpaResults->findEnrollmentIdentity(
            $command->enrollmentId,
            $command->schoolId,
            $command->academicYearId,
        );
        if ($enrollment === null) {
            throw TermResultEnrollmentNotFoundException::forId($command->enrollmentId);
        }

        return $this->rebuild->rebuild($command, $enrollment, $mode, $idempotencyKey);
    }
}
