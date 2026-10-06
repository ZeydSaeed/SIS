<?php

namespace App\Domain\Organization\ValueObjects;

enum DirectorateStatus: int
{
    case Active = 1;
    case Inactive = 2;
    /** مؤرشف — kept for history, hidden from operational lists (like inactive). */
    case Archived = 3;
}
