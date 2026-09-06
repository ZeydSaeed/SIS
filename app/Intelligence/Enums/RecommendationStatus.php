<?php

namespace App\Intelligence\Enums;

enum RecommendationStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Executed = 'executed';
    case RolledBack = 'rolled_back';
    case Expired = 'expired';
}
