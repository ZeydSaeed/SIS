<?php

namespace App\Domain\Student\ValueObjects;

enum StudentDocumentStatus: int
{
    case Active = 1;
    case Voided = 2;
}
