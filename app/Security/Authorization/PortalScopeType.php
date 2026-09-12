<?php

namespace App\Security\Authorization;

use App\Domain\Portal\ValueObjects\PortalScopeType as DomainPortalScopeType;

/**
 * @deprecated Prefer Domain\Portal\ValueObjects\PortalScopeType — kept as alias for existing callers.
 */
final class PortalScopeType
{
    public const STUDENT = DomainPortalScopeType::STUDENT;

    public const GUARDIAN = DomainPortalScopeType::GUARDIAN;
}
