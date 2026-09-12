<?php

namespace App\Domain\Results\ValueObjects;

enum ResultsLifecycleStatus: int
{
    case Calculated = 1;
    case Finalized = 2;
    case Superseded = 3;
}
