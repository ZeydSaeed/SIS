<?php

namespace App\Domain\Admission\ValueObjects;

/**
 * Admission channel (قناة القبول).
 * 1 = academic → vocational transfer, 2 = vocational school intake.
 */
enum ApplicationRequestKind: int
{
    case AcademicTransfer = 1;
    case Vocational = 2;
}
