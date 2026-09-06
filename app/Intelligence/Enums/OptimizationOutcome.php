<?php

namespace App\Intelligence\Enums;

enum OptimizationOutcome: string
{
    case Pending = 'pending';
    case Success = 'success';
    case Partial = 'partial';
    case Failure = 'failure';
    case RolledBack = 'rolled_back';
}
