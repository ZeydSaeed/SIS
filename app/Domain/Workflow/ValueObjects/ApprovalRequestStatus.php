<?php

namespace App\Domain\Workflow\ValueObjects;

final class ApprovalRequestStatus
{
    public const Pending = 1;

    public const Approved = 2;

    public const Rejected = 3;

    public const Cancelled = 4;
}
