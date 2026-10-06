<?php

namespace App\Domain\Teachers\ValueObjects;

/** teachers.teaching_assignments.status — ended rows keep history (effective_to). */
final class TeachingAssignmentStatus
{
    public const Active = 1;

    public const Ended = 2;
}
