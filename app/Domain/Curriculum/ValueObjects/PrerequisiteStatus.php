<?php

namespace App\Domain\Curriculum\ValueObjects;

enum PrerequisiteStatus: int
{
    case Active = 1;
    case Inactive = 2;
}
