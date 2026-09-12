<?php

namespace App\Domain\Results\ValueObjects;

enum TermResultLifecycleStatus: int
{
    case Calculated = 1;
    case Finalized = 2;
    case Superseded = 3;
}
