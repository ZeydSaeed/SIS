<?php

namespace App\Domain\Admission\ValueObjects;

/** application_periods.status */
enum ApplicationPeriodStatus: int
{
    case Inactive = 0;
    case Active = 1;
    case Archived = 2;
}
