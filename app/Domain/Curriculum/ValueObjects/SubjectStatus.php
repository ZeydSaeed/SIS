<?php

namespace App\Domain\Curriculum\ValueObjects;

enum SubjectStatus: int
{
    case Active = 1;
    case Inactive = 2;
}
