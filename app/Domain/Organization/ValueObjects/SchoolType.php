<?php

namespace App\Domain\Organization\ValueObjects;

/**
 * organization.schools.school_type — only the vocational type is in use today.
 */
enum SchoolType: int
{
    case Vocational = 2;
}
