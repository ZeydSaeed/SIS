<?php

namespace App\Domain\Vocational\ValueObjects;

enum VocationalCatalogStatus: int
{
    case Active = 1;
    case Inactive = 2;
}
