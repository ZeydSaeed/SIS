<?php

namespace App\Domain\Communication\ValueObjects;

final class MessageStatus
{
    public const Queued = 1;

    public const Sent = 2;

    public const Failed = 3;
}
