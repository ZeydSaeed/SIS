<?php

namespace App\Domain\Enrollment\ValueObjects;

enum EnrollmentSubjectStatus: int
{
    case Active = 1;
    case Inactive = 2;
}
