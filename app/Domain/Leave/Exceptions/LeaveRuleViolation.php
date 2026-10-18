<?php

namespace App\Domain\Leave\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

/**
 * A broken leave rule. The code is shown to the user in Arabic (errors catalogue); a `*_not_found` code answers
 * 404 — a request of another school or person is indistinguishable from a missing one.
 */
final class LeaveRuleViolation extends SisDomainException
{
    public static function of(string $code, ?string $message = null): self
    {
        return new self($message ?? $code, 'leave.'.$code);
    }

    public static function requestNotFound(): self
    {
        return self::of('request_not_found');
    }

    public static function typeNotFound(): self
    {
        return self::of('type_not_found');
    }

    public static function transition(string $action, int $status): self
    {
        return self::of('transition_invalid', "Action «{$action}» is not allowed in status {$status}.");
    }

    public static function versionConflict(): self
    {
        return self::of('version_conflict');
    }
}
