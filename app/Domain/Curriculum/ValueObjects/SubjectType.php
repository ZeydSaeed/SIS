<?php

namespace App\Domain\Curriculum\ValueObjects;

enum SubjectType: int
{
    case Core = 1;
    case Elective = 2;
    case Practical = 3;
}
