<?php

namespace App\Infrastructure\Communication;

use App\Application\Communication\Contracts\OutboundMessagePort;
use App\Domain\Communication\Data\MessageSnapshot;

/**
 * Local / null provider — records delivery success without network IO.
 */
final class LocalOutboundMessageAdapter implements OutboundMessagePort
{
    public function deliver(MessageSnapshot $message): array
    {
        return ['ok' => true];
    }
}
