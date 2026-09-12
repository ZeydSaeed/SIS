<?php

namespace App\Domain\Transfers\ValueObjects;

final class TransferRequestStatus
{
    public const Pending = 1;

    public const Approved = 2;

    public const Rejected = 3;

    public const Completed = 4;

    public const Cancelled = 5;
}
