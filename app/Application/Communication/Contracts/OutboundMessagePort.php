<?php

namespace App\Application\Communication\Contracts;

use App\Domain\Communication\Data\MessageSnapshot;

/**
 * Outbound delivery port — Local adapter only in COM-U06 (no SMTP/SMS).
 */
interface OutboundMessagePort
{
    /**
     * @return array{ok: bool, error?: string}
     */
    public function deliver(MessageSnapshot $message): array;
}
