<?php

namespace App\Infrastructure\Graduation;

use App\Application\Graduation\Contracts\GraduationAuthorityPort;
use App\Domain\Graduation\Exceptions\GraduationAuthorityDeniedException;
use App\Domain\Graduation\ValueObjects\GraduationAction;
use App\Security\Context\SchoolContext;

/**
 * Fail-closed actor allow-lists (config) until HD-31-G locks Permission.php catalog.
 */
final class FailClosedGraduationAuthority implements GraduationAuthorityPort
{
    public function __construct(
        private readonly SchoolContext $schoolContext,
    ) {}

    public function assertSchoolMatches(int $commandSchoolId): void
    {
        $ctx = $this->schoolContext->id();
        if ($ctx === null || $ctx !== $commandSchoolId) {
            throw GraduationAuthorityDeniedException::schoolMismatch();
        }
    }

    public function assertCan(GraduationAction $action, int $actorUserId, int $schoolId): void
    {
        $this->assertSchoolMatches($schoolId);

        if ($action === GraduationAction::PublishAward) {
            throw GraduationAuthorityDeniedException::forAction($action->value);
        }

        /** @var list<int|string> $allowed */
        $allowed = config('sis.graduation.authority.'.$action->value, []);
        if (! is_array($allowed) || $allowed === []) {
            throw GraduationAuthorityDeniedException::catalogNotConfigured();
        }

        $normalized = array_map(static fn ($id): int => (int) $id, $allowed);
        if (! in_array($actorUserId, $normalized, true)) {
            throw GraduationAuthorityDeniedException::forAction($action->value);
        }
    }
}
