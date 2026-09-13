<?php

namespace App\Domain\Curriculum\ValueObjects;

enum CurriculumStatus: int
{
    case Active = 1;
    case Inactive = 2;
}
