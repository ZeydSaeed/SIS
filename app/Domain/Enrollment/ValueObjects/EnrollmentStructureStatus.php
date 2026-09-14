<?php

namespace App\Domain\Enrollment\ValueObjects;

enum EnrollmentStructureStatus: int
{
    case Active = 1;
    case Inactive = 2;
}
