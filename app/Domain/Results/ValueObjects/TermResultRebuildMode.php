<?php

namespace App\Domain\Results\ValueObjects;

enum TermResultRebuildMode: string
{
    case Operational = 'operational';
    case Official = 'official';
}
