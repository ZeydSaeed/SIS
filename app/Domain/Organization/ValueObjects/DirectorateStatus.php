<?php

namespace App\Domain\Organization\ValueObjects;

enum DirectorateStatus: int
{
    case Active = 1;
    case Inactive = 2;
}
