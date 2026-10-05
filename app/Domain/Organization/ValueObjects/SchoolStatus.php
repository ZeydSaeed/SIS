<?php

namespace App\Domain\Organization\ValueObjects;

enum SchoolStatus: int
{
    case Active = 1;
    case Inactive = 2;
}
